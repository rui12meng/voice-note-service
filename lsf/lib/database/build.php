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
    protected function parseWhereItem($key, $val){
        $whereStr = '';
        if(is_array($val)){
            if(is_string($val[0])) {
                if(preg_match('/^(EQ|NEQ|GT|EGT|LT|ELT)$/i', $val[0])) { // 比较运算
                    $whereStr.= $key.' '.$this->comparison[strtolower($val[0])] . ' ' . $this->parseValue($val[1]);
                }elseif(preg_match('/^(NOTLIKE|LIKE)$/i', $val[0])){ // 模糊查找
                    if(is_array($val[1])){
                        $likeLogic = isset($val[2]) ? strtoupper($val[2]) : 'OR';
                        if(in_array($likeLogic, ['AND', 'OR', 'XOR'])){
                            $likeStr = $this->comparison[strtolower($val[0])];
                            $like = [];
                            foreach($val[1] as $item){
                                $like[] = $key . ' ' . $likeStr . ' ' . $this->parseValue($item);
                            }
                            $whereStr .= '(' . implode(' ' . $likeLogic . ' ', $like) . ')';
                        }
                    }else{
                        $whereStr.= $key . ' ' . $this->comparison[strtolower($val[0])] . ' ' . $this->parseValue($val[1]);
                    }
                }elseif('exp' == strtolower($val[0])){ // 使用表达式
                    $whereStr.= ' (' . $key . ' ' . $val[1] . ') ';
                }elseif(preg_match('/IN/i', $val[0])){ // IN 运算
                    if(isset($val[2]) && 'exp' == $val[2]) {
                        $whereStr.= $key . ' ' . strtoupper($val[0]) . ' ' . $val[1];
                    }else{
                        if(is_string($val[1])){
                            $val[1] = explode(',', $val[1]);
                        }
                        $zone = implode(',', $this->parseValue($val[1]));
                        $whereStr.= $key . ' ' . strtoupper($val[0]) . ' (' . $zone . ')';
                    }
                }elseif(preg_match('/BETWEEN/i', $val[0])){ // BETWEEN运算
                    $data = is_string($val[1]) ? explode(',', $val[1]) : $val[1];
                    $whereStr.=  ' (' . $key . ' ' . strtoupper($val[0]) . ' ' . $this->parseValue($data[0]) . ' AND ' . $this->parseValue($data[1]) . ' )';
                }else{
                    throw new \Exception("param where error : $val[0]");
                }
            }else {
                $count = count($val);
                $rule = isset($val[$count-1]) ? strtoupper($val[$count-1]) : '';
                if(in_array($rule, ['AND','OR','XOR'])) {
                    $count = $count - 1;
                }else{
                    $rule = 'AND';
                }
                for($i=0;$i<$count;$i++){
                    $data = is_array($val[$i]) ? $val[$i][1] : $val[$i];
                    if('exp' == strtolower($val[$i][0])) {
                        $whereStr.= '(' . $key . ' ' . $data.') ' . $rule . ' ';
                    }else{
                        $op = is_array($val[$i]) ? $this->comparison[strtolower($val[$i][0])] : '=';
                        $whereStr.= '(' . $key . ' ' . $op . ' ' . $this->parseValue($data) . ') ' . $rule . ' ';
                    }
                }
                $whereStr = substr($whereStr, 0, -4);
            }
        }else{
            $whereStr.= $key . ' = ' . $this->parseValue($val);
        }
        return $whereStr;
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