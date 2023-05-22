<?php
declare(strict_types = 1);

namespace BusyPHP\ide\generator;

/**
 * 参数类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2023 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2023/5/21 16:50 Argument.php $
 */
class Argument
{
    private string            $name;
    
    private string|array|null $type;
    
    private string|null       $default;
    
    
    /**
     * 构造函数
     * @param string            $name 参数名称
     * @param string|array|null $type 参数类型
     * @param string|null       $default 参数默认值
     */
    public function __construct(string $name, string|array $type = null, string $default = null)
    {
        $this->name    = $name;
        $this->type    = $type;
        $this->default = $default;
    }
    
    
    /**
     * 生成参数
     * @return string
     */
    public function build() : string
    {
        $type = '';
        if ($this->type) {
            $type = sprintf('%s ', implode('|', array_filter((array) $this->type)));
        }
        
        $default = '';
        if (null !== $this->default) {
            $default = sprintf(' = %s', $this->default);
        }
        
        return sprintf('%s$%s%s', $type, $this->name, $default);
    }
    
    
    public function __toString() : string
    {
        return $this->build();
    }
}