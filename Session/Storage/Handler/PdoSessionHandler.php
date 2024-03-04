<?php
namespace Fontai\Bundle\SecurityBundle\Session\Storage\Handler;

use Symfony\Component\HttpFoundation\RequestStack;


class PdoSessionHandler implements \SessionHandlerInterface
{
  protected $requestStack;

  /**
   * No locking is done. This means sessions are prone to loss of data due to
   * race conditions of concurrent requests to the same session. The last session
   * write will win in this case. It might be useful when you implement your own
   * logic to deal with this like an optimistic approach.
   */
  const LOCK_NONE = 0;

  /**
   * Creates an application-level lock on a session. The disadvantage is that the
   * lock is not enforced by the database and thus other, unaware parts of the
   * application could still concurrently modify the session. The advantage is it
   * does not require a transaction.
   */
  const LOCK_ADVISORY = 1;

  /**
   * Issues a real row lock. Since it uses a transaction between opening and
   * closing a session, you have to be careful when you use same database connection
   * that you also use for your application logic. This mode is the default because
   * it's the only reliable solution across DBMSs.
   */
  const LOCK_TRANSACTIONAL = 2;

  /**
   * @var \PDO PDO instance
   */
  protected $pdo;

  /**
   * @var string Database driver
   */
  protected $driver;

  /**
   * @var string Table name
   */
  protected $table = 'session';

  /**
   * @var string Column for session id
   */
  protected $idCol = 'id';

  /**
   * @var string Column for session data
   */
  protected $dataCol = 'data';

  /**
   * @var string Column for lifetime
   */
  protected $lifetimeCol = 'lifetime';

  /**
   * @var string Column for timestamp
   */
  protected $createdAtCol = 'created_at';

  /**
   * @var string Column for timestamp
   */
  protected $updatedAtCol = 'updated_at';

  /**
   * @var string Column for last IP address
   */
  protected $ipCol = 'last_ip';

  /**
   * @var string Column for last User Agent
   */
  protected $userAgentCol = 'last_useragent';

  /**
   * @var int The strategy for locking, see constants
   */
  protected $lockMode = self::LOCK_TRANSACTIONAL;

  /**
   * It's an array to support multiple reads before closing which is manual, non-standard usage.
   *
   * @var \PDOStatement[] An array of statements to release advisory locks
   */
  protected $unlockStatements = [];

  /**
   * @var bool True when the current session exists but expired according to session.gc_maxlifetime
   */
  protected $sessionExpired = FALSE;

  /**
   * @var bool Whether a transaction is active
   */
  protected $inTransaction = FALSE;

  /**
   * @var bool Whether gc() has been called
   */
  protected $gcCalled = FALSE;

  /**
   * Pass an existing database connection as PDO instance.
   *
   * List of available options:
   *  * db_table: The name of the table [default: sessions]
   *  * db_id_col: The column where to store the session id [default: id]
   *  * db_data_col: The column where to store the session data [default: data]
   *  * db_lifetime_col: The column where to store the lifetime [default: lifetime]
   *  * db_created_at_col: The column where to store the timestamp [default: created_at]
   *  * db_updated_at_col: The column where to store the timestamp [default: updated_at]
   *  * db_ip_col: The column where to store the client ip [default: last_ip]
   *  * db_useragent_col: The column where to store the client user agent [default: last_useragent]
   *  * lock_mode: The strategy for locking, see constants [default: LOCK_TRANSACTIONAL]
   *
   * @param \PDO  $pdo A \PDO instance
   * @param array $options  An associative array of options
   *
   * @throws \InvalidArgumentException When PDO error mode is not PDO::ERRMODE_EXCEPTION
   */
  public function __construct($pdo, array $options = [], RequestStack $requestStack)
  {
    $this->requestStack = $requestStack;

    if (\PDO::ERRMODE_EXCEPTION !== $pdo->getAttribute(\PDO::ATTR_ERRMODE))
    {
      throw new \InvalidArgumentException(sprintf('"%s" requires PDO error mode attribute be set to throw Exceptions (i.e. $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION))', __CLASS__));
    }

    $this->pdo = $pdo;
    $this->checkPdoDriver();

    $this->table             = isset($options['db_table'])              ? $options['db_table']              : $this->table;
    $this->idCol             = isset($options['db_id_col'])             ? $options['db_id_col']             : $this->idCol;
    $this->dataCol           = isset($options['db_data_col'])           ? $options['db_data_col']           : $this->dataCol;
    $this->lifetimeCol       = isset($options['db_lifetime_col'])       ? $options['db_lifetime_col']       : $this->lifetimeCol;
    $this->createdAtCol      = isset($options['db_created_at_col'])     ? $options['db_created_at_col']     : $this->createdAtCol;
    $this->updatedAtCol      = isset($options['db_updated_at_col'])     ? $options['db_updated_at_col']     : $this->updatedAtCol;
    $this->ipCol             = isset($options['db_ip_col'])             ? $options['db_ip_col']             : $this->ipCol;
    $this->userAgentCol      = isset($options['db_useragent_col'])      ? $options['db_useragent_col']      : $this->userAgentCol;
    $this->lockMode          = isset($options['lock_mode'])             ? $options['lock_mode']             : $this->lockMode;
  }

  /**
   * Returns true when the current session exists but expired according to session.gc_maxlifetime.
   *
   * Can be used to distinguish between a new session and one that expired due to inactivity.
   *
   * @return bool Whether current session expired
   */
  public function isSessionExpired()
  {
    return $this->sessionExpired;
  }

  /**
   * {@inheritdoc}
   */
  public function open($savePath, $sessionName)
  {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function read($sessionId)
  {
    try
    {
      return $this->doRead($sessionId);
    }
    catch (\PDOException $e)
    {
      $this->rollback();
      
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function gc($maxlifetime)
  {
    // We delay gc() to close() so that it is executed outside the transactional and blocking read-write process.
    // This way, pruning expired sessions does not block them from being started while the current session is used.
    $this->gcCalled = TRUE;

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function destroy($sessionId)
  {
    // delete the record associated with this id
    $sql = "DELETE FROM $this->table WHERE $this->idCol = :id";

    try
    {
      $stmt = $this->pdo->prepare($sql);
      $stmt->bindParam(':id', $sessionId, \PDO::PARAM_STR);
      $stmt->execute();
    }
    catch (\PDOException $e)
    {
      $this->rollback();

      throw $e;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function write($sessionId, $data)
  {
    $maxlifetime = (int) ini_get('session.gc_maxlifetime');

    try {
      // We use a single MERGE SQL query when supported by the database.
      $mergeStmt = $this->getMergeStatement($sessionId, $data, $maxlifetime);
      if (NULL !== $mergeStmt)
      {
        $mergeStmt->execute();

        return TRUE;
      }

      $updateStmt = $this->pdo->prepare(
        "UPDATE $this->table
        SET $this->dataCol  = :data,
        $this->lifetimeCol  = :lifetime,
        $this->updatedAtCol = :updated_at,
        $this->ipCol        = IF(@internal_request := :internal_request, $this->ipCol, :ip),
        $this->userAgentCol = IF(@internal_request, $this->userAgentCol, :useragent)
        WHERE $this->idCol  = :id"
      );

      $this->bindStatementParams($updateStmt, $sessionId, $data, $maxlifetime);

      $updateStmt->execute();

      // When MERGE is not supported, like in Postgres < 9.5, we have to use this approach that can result in
      // duplicate key errors when the same session is written simultaneously (given the LOCK_NONE behavior).
      // We can just catch such an error and re-execute the update. This is similar to a serializable
      // transaction with retry logic on serialization failures but without the overhead and without possible
      // false positives due to longer gap locking.
      if (!$updateStmt->rowCount())
      {
        try
        {
          $insertStmt = $this->getInsertStatement($sessionId, $data, $maxlifetime);
          $insertStmt->execute();
        }
        catch (\PDOException $e)
        {
          // Handle integrity violation SQLSTATE 23000 (or a subclass like 23505 in Postgres) for duplicate keys
          if (0 === strpos($e->getCode(), '23'))
          {
            $updateStmt->execute();
          }
          else
          {
            throw $e;
          }
        }
      }
    }
    catch (\PDOException $e)
    {
      $this->rollback();

      throw $e;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function close()
  {
    $this->commit();

    while ($unlockStmt = array_shift($this->unlockStatements))
    {
      $unlockStmt->execute();
    }

    if ($this->gcCalled)
    {
      $this->gcCalled = FALSE;

      // delete the session records that have expired
      $sql = "DELETE FROM $this->table WHERE $this->lifetimeCol < :time - UNIX_TIMESTAMP($this->updatedAtCol)";

      $dt = new \DateTime();

      $stmt = $this->pdo->prepare($sql);
      $stmt->bindValue(':time', $dt->format('U'), \PDO::PARAM_INT);
      $stmt->execute();
    }

    return TRUE;
  }

  /**
   * Helper method to begin a transaction.
   *
   * Also MySQLs default isolation, REPEATABLE READ, causes deadlock for different sessions
   * due to http://www.mysqlperformanceblog.com/2013/12/12/one-more-innodb-gap-lock-to-avoid/ .
   * So we change it to READ COMMITTED.
   */
  protected function beginTransaction()
  {
    if ($this->inTransaction)
    {
      return;
    }
    
    if ('mysql' === $this->driver)
    {
      $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
    }

    $this->pdo->beginTransaction();

    $this->inTransaction = TRUE;
  }

  /**
   * Helper method to commit a transaction.
   */
  protected function commit()
  {
    if (!$this->inTransaction)
    {
      return;
    }
    
    try
    {
      // commit read-write transaction which also releases the lock
      $this->pdo->commit();
      $this->inTransaction = FALSE;
    }
    catch (\PDOException $e)
    {
      $this->rollback();

      throw $e;
    }
  }

  /**
   * Helper method to rollback a transaction.
   */
  protected function rollback()
  {
    // We only need to rollback if we are in a transaction. Otherwise the resulting
    // error would hide the real problem why rollback was called. We might not be
    // in a transaction when not using the transactional locking behavior or when
    // two callbacks (e.g. destroy and write) are invoked that both fail.
    if (!$this->inTransaction)
    {
      return;
    }
    
    $this->pdo->rollBack();
    $this->inTransaction = FALSE;
  }

  /**
   * Reads the session data in respect to the different locking strategies.
   *
   * We need to make sure we do not return session data that is already considered garbage according
   * to the session.gc_maxlifetime setting because gc() is called after read() and only sometimes.
   *
   * @param string $sessionId Session ID
   *
   * @return string The session data
   */
  protected function doRead($sessionId)
  {
    $this->sessionExpired = FALSE;

    if (self::LOCK_ADVISORY === $this->lockMode)
    {
      $this->unlockStatements[] = $this->doAdvisoryLock($sessionId);
    }

    $selectStmt = $this->getSelectStatement($sessionId);

    do {
      $selectStmt->execute();
      $sessionRows = $selectStmt->fetchAll(\PDO::FETCH_NUM);

      if ($sessionRows)
      {
        if ($sessionRows[0][1] + (new \DateTime($sessionRows[0][2]))->format('U') < time())
        {
          $this->sessionExpired = TRUE;

          return '';
        }

        return is_resource($sessionRows[0][0]) ? stream_get_contents($sessionRows[0][0]) : $sessionRows[0][0];
      }

      if (self::LOCK_TRANSACTIONAL === $this->lockMode)
      {
        // Exclusive-reading of non-existent rows does not block, so we need to do an insert to block
        // until other connections to the session are committed.
        try {
          $insertStmt = $this->getInsertStatement($sessionId, '', 0);
          $insertStmt->execute();
        }
        catch (\PDOException $e)
        {
          // Catch duplicate key error because other connection created the session already.
          // It would only not be the case when the other connection destroyed the session.
          if (0 === strpos($e->getCode(), '23'))
          {
            // Retrieve finished session data written by concurrent connection by restarting the loop.
            // We have to start a new transaction as a failed query will mark the current transaction as
            // aborted in PostgreSQL and disallow further queries within it.
            $this->rollback();
            $this->beginTransaction();

            continue;
          }

          throw $e;
        }
      }

      return '';
    } while (TRUE);
  }

  /**
   * Executes an application-level lock on the database.
   *
   * @param string $sessionId Session ID
   *
   * @return \PDOStatement The statement that needs to be executed later to release the lock
   *
   * @throws \DomainException When an unsupported PDO driver is used
   */
  protected function doAdvisoryLock($sessionId)
  {
    switch ($this->driver)
    {
      case 'mysql':
        // should we handle the return value? 0 on timeout, null on error
        // we use a timeout of 50 seconds which is also the default for innodb_lock_wait_timeout
        $stmt = $this->pdo->prepare('SELECT GET_LOCK(:key, 50)');
        $stmt->bindValue(':key', $sessionId, \PDO::PARAM_STR);
        $stmt->execute();

        $releaseStmt = $this->pdo->prepare('DO RELEASE_LOCK(:key)');
        $releaseStmt->bindValue(':key', $sessionId, \PDO::PARAM_STR);

        return $releaseStmt;

      case 'pgsql':
        // Obtaining an exclusive session level advisory lock requires an integer key.
        // When session.sid_bits_per_character > 4, the session id can contain non-hex-characters.
        // So we cannot just use hexdec().
        if (4 === \PHP_INT_SIZE)
        {
          $sessionInt1 = $this->convertStringToInt($sessionId);
          $sessionInt2 = $this->convertStringToInt(substr($sessionId, 4, 4));

          $stmt = $this->pdo->prepare('SELECT pg_advisory_lock(:key1, :key2)');
          $stmt->bindValue(':key1', $sessionInt1, \PDO::PARAM_INT);
          $stmt->bindValue(':key2', $sessionInt2, \PDO::PARAM_INT);
          $stmt->execute();

          $releaseStmt = $this->pdo->prepare('SELECT pg_advisory_unlock(:key1, :key2)');
          $releaseStmt->bindValue(':key1', $sessionInt1, \PDO::PARAM_INT);
          $releaseStmt->bindValue(':key2', $sessionInt2, \PDO::PARAM_INT);
        }
        else
        {
          $sessionBigInt = $this->convertStringToInt($sessionId);

          $stmt = $this->pdo->prepare('SELECT pg_advisory_lock(:key)');
          $stmt->bindValue(':key', $sessionBigInt, \PDO::PARAM_INT);
          $stmt->execute();

          $releaseStmt = $this->pdo->prepare('SELECT pg_advisory_unlock(:key)');
          $releaseStmt->bindValue(':key', $sessionBigInt, \PDO::PARAM_INT);
        }

        return $releaseStmt;

      default:
        throw new \DomainException(sprintf('Advisory locks are currently not implemented for PDO driver "%s".', $this->driver));
    }
  }

  /**
   * Encodes the first 4 (when PHP_INT_SIZE == 4) or 8 characters of the string as an integer.
   *
   * Keep in mind, PHP integers are signed.
   *
   * @param string $string
   *
   * @return int
   */
  protected function convertStringToInt($string)
  {
    if (4 === \PHP_INT_SIZE)
    {
      return (ord($string[3]) << 24) + (ord($string[2]) << 16) + (ord($string[1]) << 8) + ord($string[0]);
    }

    $int1 = (ord($string[7]) << 24) + (ord($string[6]) << 16) + (ord($string[5]) << 8) + ord($string[4]);
    $int2 = (ord($string[3]) << 24) + (ord($string[2]) << 16) + (ord($string[1]) << 8) + ord($string[0]);

    return $int2 + ($int1 << 32);
  }

  /**
   * Return a locking or nonlocking SQL query to read session information.
   *
   * @return string The SQL string
   *
   * @throws \DomainException When an unsupported PDO driver is used
   */
  protected function getSelectStatement($sessionId)
  {
    $sql = "SELECT $this->dataCol, $this->lifetimeCol, $this->updatedAtCol 
      FROM $this->table
      WHERE $this->idCol = :id";

    if (self::LOCK_TRANSACTIONAL === $this->lockMode)
    {
      $this->beginTransaction(); 
      $sql .= " FOR UPDATE";
    }

    $stmt = $this->pdo->prepare($sql);
    $stmt->bindParam(':id', $sessionId, \PDO::PARAM_STR);

    return $stmt;
  }

  /**
   * Returns a insert statement for writing session data.
   *
   * @param string $sessionId   Session ID
   * @param string $data        Encoded session data
   * @param int    $maxlifetime session.gc_maxlifetime
   *
   * @return \PDOStatement|null The merge statement or null when not supported
   */
  protected function getInsertStatement($sessionId, $data, $maxlifetime)
  {
    $stmt = $this->pdo->prepare(
      "INSERT INTO $this->table (
        $this->idCol,
        $this->dataCol,
        $this->lifetimeCol,
        $this->createdAtCol,
        $this->updatedAtCol,
        $this->ipCol,
        $this->userAgentCol
      )
      VALUES (
        :id,
        :data,
        :lifetime,
        :created_at,
        :updated_at,
        :ip,
        IF(:internal_request, NULL, :useragent)
      )"
    );

    $this->bindStatementParams($stmt, $sessionId, $data, $maxlifetime);

    return $stmt;
  }

  /**
   * Returns a merge/upsert (i.e. insert or update) statement when supported by the database for writing session data.
   *
   * @param string $sessionId   Session ID
   * @param string $data        Encoded session data
   * @param int    $maxlifetime session.gc_maxlifetime
   *
   * @return \PDOStatement|null The merge statement or null when not supported
   */
  protected function getMergeStatement($sessionId, $data, $maxlifetime)
  {
    $sql = NULL;

    switch (TRUE)
    {
      case 'mysql' === $this->driver:
        $sql = "INSERT INTO $this->table (
            $this->idCol,
            $this->dataCol,
            $this->lifetimeCol,
            $this->createdAtCol,
            $this->updatedAtCol,
            $this->ipCol,
            $this->userAgentCol
          )
          VALUES (
            :id,
            :data,
            :lifetime,
            :created_at,
            :updated_at,
            :ip,
            IF(@internal_request := :internal_request, NULL, :useragent)
          )
          ON DUPLICATE KEY UPDATE
          $this->dataCol      = VALUES($this->dataCol),
          $this->lifetimeCol  = VALUES($this->lifetimeCol),
          $this->updatedAtCol = VALUES($this->updatedAtCol),
          $this->ipCol        = IF(@internal_request, $this->ipCol, VALUES($this->ipCol)),
          $this->userAgentCol = IF(@internal_request, $this->userAgentCol, VALUES($this->userAgentCol))";
        break;

      case 'pgsql' === $this->driver && version_compare($this->pdo->getAttribute(\PDO::ATTR_SERVER_VERSION), '9.5', '>='):
        $sql = "INSERT INTO $this->table (
            $this->idCol,
            $this->dataCol,
            $this->lifetimeCol,
            $this->createdAtCol,
            $this->updatedAtCol,
            $this->ipCol,
            $this->userAgentCol
          )
          VALUES (
            :id,
            :data,
            :lifetime,
            :created_at,
            :updated_at,
            :ip,
            IF(@internal_request := :internal_request, NULL, :useragent)
          )
          ON CONFLICT ($this->idCol) DO UPDATE SET (
            $this->dataCol,
            $this->lifetimeCol,
            $this->updatedAtCol,
            $this->ipCol,
            $this->userAgentCol
          ) = (
            EXCLUDED.$this->dataCol,
            EXCLUDED.$this->lifetimeCol,
            EXCLUDED.$this->updatedAtCol,
            IF(@internal_request, $this->ipCol, EXCLUDED.$this->ipCol),
            IF(@internal_request, $this->userAgentCol, EXCLUDED.$this->userAgentCol)
          )";
        break;
    }

    if (NULL !== $sql)
    {
      $stmt = $this->pdo->prepare($sql);

      $this->bindStatementParams($stmt, $sessionId, $data, $maxlifetime);

      return $stmt;
    }
  }

  protected function bindStatementParams($stmt, $sessionId, $data, $maxlifetime)
  {
    $dt = new \DateTime();

    $stmt->bindParam(':id',               $sessionId,                  \PDO::PARAM_STR);
    $stmt->bindParam(':data',             $data,                       \PDO::PARAM_LOB);
    $stmt->bindParam(':lifetime',         $maxlifetime,                \PDO::PARAM_INT);
    $stmt->bindValue(':created_at',       $dt->format('Y-m-d H:i:s'),  \PDO::PARAM_STR);
    $stmt->bindValue(':updated_at',       $dt->format('Y-m-d H:i:s'),  \PDO::PARAM_STR);
    $stmt->bindValue(':ip',               $this->getClientIp(),        \PDO::PARAM_STR);
    $stmt->bindValue(':useragent',        $this->getClientUserAgent(), \PDO::PARAM_STR);
    $stmt->bindValue(':internal_request', $this->isInternalRequest(),  \PDO::PARAM_INT);
  }

  protected function checkPdoDriver()
  {
    $this->driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    
    if (!in_array($this->driver, ['mysql', 'pgsql']))
    {
      throw new \DomainException('Unsupported driver');
    }
  }

  protected function isInternalRequest()
  {
    $request = $this->requestStack->getCurrentRequest();

    return $request->getClientIp() == $request->server->get('SERVER_ADDR');
  }

  protected function getClientIp()
  {
    return $this->requestStack->getCurrentRequest()->getClientIp();
  }

  protected function getClientUserAgent()
  {
    return $this->requestStack->getCurrentRequest()->headers->get('User-Agent');
  }
}