<?php
namespace Lsf\Intfc;

/**
 * 数据库接口类
 * @author
 * $Id: database.php $
 */

interface DataBase
{
    function connect($config);
    function getLastSql();
    function getLastInsertId();
    function getErrorNo();
    function getErrorMsg();
}