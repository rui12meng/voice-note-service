<?php
namespace Database;

/**
 * SQL构建器
 * @author mr
 * $Id: build.php $
 */

class Build
{
    // 数据库表达式
    protected $comparison = [
        'eq'        => '=',
        'neq'       => '<>',
        'gt'        => '>',
        'egt'       => '>=',
        'lt'        => '<',
        'elt'       => '<=',
        'notlike'   => 'NOT LIKE',
        'like'      => 'LIKE',
        'in'        => 'IN',
        'notin'     => 'NOT IN'
    ];

    public function parseUpdateData($data){
        foreach ($data as $key=>$val){
            if(is_array($val) && 'exp' == $val[0]){
                $set[] = $this->parseKey($key) . '=' . $val[1];
            }elseif(is_scalar($val) || is_null(($val))) { // 过滤非标量数据
                $set[] = $this->parseKey($key) . '=' . $this->parseValue($val);
            }
        }
        return ' ' . implode(',', $set);
    }

    public function parseInsertData($data){
        foreach($data as $key => $val){
            if(is_array($val) && 'exp' == $val[0]){
                $fields[] = $this->parseKey($key);
                $values[] = $val[1];
            }elseif(is_scalar($val) || is_null(($val))) { // 过滤非标量数据
                $fields[] = $this->parseKey($key);
                $values[] = $this->parseValue($val);
            }
        }
        return array('(' . implode(',', $fields) . ')', '('. implode(',', $values) . ')');
    }

    /**
     * 字段名分析
     * @access protected
     * @param  string     $key
     * @return string
     */
    protected function parseKey(&$key){
        return '`' . $key . '`';
    }

    /**
     * value分析
     * @access protected
     * @param  mixed      $value
     * @return string
     */
    protected function parseValue($value){
        if(is_string($value)) {
            $value =  '\'' . $this->escapeString($value) . '\'';
        }elseif(isset($value[0]) && is_string($value[0]) && strtolower($value[0]) == 'exp'){
            $value =  $this->escapeString($value[1]);
        }elseif(is_array($value)) {
            $value =  array_map(array($this, 'parseValue'),$value);
        }elseif(is_bool($value)){
            $value =  $value ? '1' : '0';
        }elseif(is_null($value)){
            $value =  'null';
        }
        return $value;
    }

    /**
     * where分析
     * @access protected
     * @param  mixed      $where
     * @return string
     */
    public function parseWhere($where){
        if(!$where){
            return '1';
        }
        $whereStr = '';
        if(is_string($where)){
            // 直接使用字符串条件
            $whereStr = $where;
        }else{ // 使用数组表达式
            $operate = isset($where['_logic']) ? strtoupper($where['_logic']) : '';
            if(in_array($operate, array('AND', 'OR', 'XOR'))){
                // 定义逻辑运算规则 例如 OR XOR AND NOT
                $operate = ' ' . $operate . ' ';
                unset($where['_logic']);
            }else{
                // 默认进行 AND 运算
                $operate = ' AND ';
            }
            foreach($where as $key => $val){
                $whereStr.= '( ';
                if(is_numeric($key)){
                    $key = '_complex';
                }
                if(0 === strpos($key, '_')){
                    // 解析特殊条件表达式
                    $whereStr.= $this->parseThinkWhere($key, $val);
                }else{
                    // 查询字段的安全过滤
                    if(!preg_match('/^[A-Z_\|\&\-.a-z0-9\(\)\,]+$/', trim($key))){
                        throw_exception(L('_EXPRESS_ERROR_') . ':' . $key);
                    }
                    // 多条件支持
                    $multi = is_array($val) && isset($val['_multi']);
                    $key = trim($key);
                    if(strpos($key, '|')) { // 支持 name|title|nickname 方式定义查询字段
                        $array =  explode('|', $key);
                        $str =  array();
                        foreach($array as $m => $k){
                            $v = $multi?$val[$m]:$val;
                            $str[] = '(' . $this->parseWhereItem($this->parseKey($k), $v) . ')';
                        }
                        $whereStr.= implode(' OR ', $str);
                    }elseif(strpos($key, '&')){
                        $array = explode('&', $key);
                        $str = [];
                        foreach ($array as $m => $k){
                            $v =  $multi ? $val[$m] : $val;
                            $str[] = '(' . $this->parseWhereItem($this->parseKey($k), $v) . ')';
                        }
                        $whereStr.= implode(' AND ', $str);
                    }else{
                        $whereStr.= $this->parseWhereItem($this->parseKey($key), $val);
                    }
                }
                $whereStr.= ' )'. $operate;
            }
            $whereStr = substr($whereStr, 0, -strlen($operate));
        }
        return empty($whereStr) ? '' : ' ' . $whereStr;
    }

    // where子单元分析
    protected function parseWhereItem($key, $val)
    {
        // 情况1: 值为 null → 转为 IS NULL
        if (is_null($val)) {
            return "$key IS NULL";
        }

        // 情况2: 非数组 → 普通等值查询
        if (!is_array($val)) {
            return "$key = " . $this->parseValue($val);
        }

        // 情况3: 数组表达式
        $operator = strtolower($val[0] ?? '');
        $value = $val[1] ?? null;

        // 特殊操作符处理
        switch ($operator) {
            // --- NULL 相关 ---
            case 'null':
                return "$key IS NULL";

            case 'notnull':
            case 'nn':
                return "$key IS NOT NULL";

            // --- 比较运算 ---
            case 'eq':
            case 'neq':
            case 'gt':
            case 'egt':
            case 'lt':
            case 'elt':
                if (!isset($this->comparison[$operator])) {
                    throw new \Exception("Unsupported comparison operator: {$val[0]}");
                }
                return "$key {$this->comparison[$operator]} " . $this->parseValue($value);

            // --- 模糊查询 ---
            case 'like':
            case 'notlike':
                if (is_array($value)) {
                    $logic = isset($val[2]) ? strtoupper($val[2]) : 'OR';
                    if (!in_array($logic, ['AND', 'OR', 'XOR'])) {
                        $logic = 'OR';
                    }
                    $likes = [];
                    foreach ($value as $item) {
                        $likes[] = "$key {$this->comparison[$operator]} " . $this->parseValue($item);
                    }
                    return '(' . implode(" $logic ", $likes) . ')';
                }
                return "$key {$this->comparison[$operator]} " . $this->parseValue($value);

            // --- IN / NOT IN ---
            case 'in':
            case 'notin':
                if (isset($val[2]) && strtolower($val[2]) === 'exp') {
                    // 表达式模式：['in', '(SELECT id FROM ...)', 'exp']
                    return "$key " . strtoupper($operator) . " $value";
                }
                if (is_string($value)) {
                    $value = explode(',', $value);
                }
                if (empty($value)) {
                    return $operator === 'in' ? '1=0' : '1=1'; // 防空 IN
                }
                $list = implode(',', array_map([$this, 'parseValue'], $value));
                return "$key " . strtoupper($operator) . " ($list)";

            // --- BETWEEN / NOT BETWEEN ---
            case 'between':
            case 'notbetween':
                $data = is_string($value) ? explode(',', $value) : (array)$value;
                if (count($data) !== 2) {
                    throw new \Exception("BETWEEN requires two values");
                }
                return "($key " . strtoupper($operator) . " " . $this->parseValue($data[0]) . " AND " . $this->parseValue($data[1]) . ")";

            // --- 原生表达式 ---
            case 'exp':
                // 注意：此处 value 应为安全的 SQL 片段
                return "($key {$value})";

            // --- 兼容旧式多条件数组（如 [['gt', 10], ['lt', 100]]）---
            default:
                // 如果第一个元素不是字符串，可能是旧式多条件
                if (!is_string($val[0])) {
                    return $this->parseMultiConditions($key, $val);
                }

                // 未知操作符
                throw new \Exception("Invalid where operator: {$val[0]}");
        }
    }

    /**
     * 处理旧式多条件数组：[['gt', 10], ['lt', 100], 'AND']
     * @param  mixed     $key
     * @param  mixed     $conditions
     * @return string
     */
    protected function parseMultiConditions($key, $conditions)
    {
        $parts = [];
        $logic = 'AND';

        // 检查最后一个是否为逻辑操作符
        $last = end($conditions);
        if (in_array(strtoupper($last), ['AND', 'OR', 'XOR'])) {
            $logic = strtoupper($last);
            array_pop($conditions);
        }

        foreach ($conditions as $cond) {
            if (is_array($cond) && isset($cond[0])) {
                $op = strtolower($cond[0]);
                $val = $cond[1] ?? null;
                if (isset($this->comparison[$op])) {
                    $parts[] = "($key {$this->comparison[$op]} " . $this->parseValue($val) . ")";
                } elseif ($op === 'exp') {
                    $parts[] = "($key {$val})";
                } else {
                    throw new \Exception("Unsupported operator in multi-condition: {$cond[0]}");
                }
            } else {
                // 非数组条件？回退为等值
                $parts[] = "($key = " . $this->parseValue($cond) . ")";
            }
        }

        return '(' . implode(" $logic ", $parts) . ')';
    }

    /**
     * 特殊条件分析
     * @access protected
     * @param  string     $key
     * @param  mixed      $val
     * @return string
     */
    protected function parseThinkWhere($key, $val){
        $whereStr = '';
        switch($key){
            case '_string':
                // 字符串模式查询条件
                $whereStr = $val;
                break;
            case '_complex':
                // 复合查询条件
                $whereStr = is_string($val) ? $val : substr($this->parseWhere($val), 6);
                break;
            case '_query':
                // 字符串模式查询条件
                parse_str($val, $where);
                if(isset($where['_logic'])){
                    $op = ' ' . strtoupper($where['_logic']). ' ';
                    unset($where['_logic']);
                }else{
                    $op = ' AND ';
                }
                $array = [];
                foreach($where as $field => $data){
                    $array[] = $this->parseKey($field) . ' = ' . $this->parseValue($data);
                }
                $whereStr = implode($op, $array);
                break;
        }
        return $whereStr;
    }

    /**
     * SQL指令安全过滤
     * @access public
     * @param  string  $str  SQL字符串
     * @return string
     */
    public function escapeString($str){
        return addslashes($str);
    }
}