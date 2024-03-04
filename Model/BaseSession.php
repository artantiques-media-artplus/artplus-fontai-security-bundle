<?php
namespace Fontai\Bundle\SecurityBundle\Model;

use App\Model;
use Propel\Runtime\Connection\ConnectionInterface;


abstract class BaseSession
{
  public function __construct()
  {
  }
  
  public function preSave(ConnectionInterface $con = NULL)
  {
    if ($this->isColumnModified(Model\Map\SessionTableMap::COL_LAST_IP))
    {
      $this->setLastGeoip(NULL);
    }

    if ($this->isColumnModified(Model\Map\SessionTableMap::COL_LAST_USERAGENT))
    {
      $this->setLastBrowser(NULL);
    }

    return TRUE;
  }
}
