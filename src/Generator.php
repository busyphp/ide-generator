<?php
declare(strict_types = 1);

namespace BusyPHP\ide\generator;

use InvalidArgumentException;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\DescriptionFactory;
use phpDocumentor\Reflection\DocBlock\Serializer;
use phpDocumentor\Reflection\DocBlock\StandardTagFactory;
use phpDocumentor\Reflection\DocBlock\Tag;
use phpDocumentor\Reflection\DocBlock\Tags\Method;
use phpDocumentor\Reflection\DocBlock\Tags\Property;
use phpDocumentor\Reflection\DocBlock\Tags\PropertyRead;
use phpDocumentor\Reflection\DocBlock\Tags\PropertyWrite;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\FqsenResolver;
use phpDocumentor\Reflection\TypeResolver;
use phpDocumentor\Reflection\Types\ContextFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use think\console\Output;

/**
 * Generator
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2023 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2023/5/22 16:05 Generator.php $
 */
abstract class Generator
{
    /**
     * 生成器名称
     * @var string
     */
    protected string $name = '';
    
    /**
     * 解析的类名称
     * @var class-string
     */
    protected string $class;
    
    /**
     * 是否覆盖
     * @var bool
     */
    protected bool $overwrite = false;
    
    /**
     * 是否重置
     * @var bool
     */
    protected bool $reset = false;
    
    /**
     * 事件调度器
     * @var EventDispatcherInterface|null
     */
    protected ?EventDispatcherInterface $dispatcher;
    
    /**
     * 消息输出器
     * @var Output|mixed|null
     */
    protected object|null $output;
    
    /**
     * @var ReflectionClass
     */
    protected ReflectionClass $reflection;
    
    /**
     * 注释属性集合
     * @var array<string, array{type: string, read: bool, write: bool, comment: string}>
     */
    protected array $docProperties = [];
    
    /**
     * 注释方法集合
     * @var array<string, array{static: bool, arguments: Argument[], return: string, comment: string}>
     */
    protected array $docMethods = [];
    
    /**
     * 真实属性集合
     * @var array<string, array{static: bool, type: string, access: int, value:mixed, comment: string}>
     */
    protected array $properties = [];
    
    /**
     * 真实方法集合
     * @var array<string, array{static: bool, arguments: Argument[], access: int, return: string, code: string, comment: string}>
     */
    protected array $methods = [];
    
    
    /**
     * 构造函数
     * @param class-string                  $class 解析的类名
     * @param bool                          $reset 是否重置文档注释
     * @param bool                          $overwrite 如果注释已存在是否覆盖
     * @param object|null                   $output 消息输出类，需要包含 $output->comment(string $message), $output->info(string $message) 2个方法
     * @param EventDispatcherInterface|null $dispatcher 事件调度器
     */
    public function __construct(string $class, bool $reset = false, bool $overwrite = false, object $output = null, EventDispatcherInterface $dispatcher = null,)
    {
        if (!str_starts_with($class, '\\')) {
            $class = '\\' . $class;
        }
        
        $this->class      = $class;
        $this->reset      = $reset;
        $this->overwrite  = $overwrite;
        $this->output     = $output;
        $this->dispatcher = $dispatcher ?: new EventDispatcher();
    }
    
    
    /**
     * 获取反射类
     * @return ReflectionClass
     */
    public function getReflection() : ReflectionClass
    {
        return $this->reflection;
    }
    
    
    /**
     * 添加注释属性
     * @param string               $name 属性名称
     * @param string[]|string|null $type 属性类型
     * @param bool                 $read 是否可读
     * @param bool                 $write 是否可写
     * @param string               $comment 属性说明
     * @return static
     */
    public function addDocProperty(string $name, array|string $type = null, bool $read = false, bool $write = false, string $comment = '') : static
    {
        if (!isset($this->docProperties[$name])) {
            if ($type) {
                $type = implode('|', array_filter((array) $type));
            }
            
            $this->docProperties[$name] = [
                'type'    => $type,
                'read'    => $read,
                'write'   => $write,
                'comment' => $comment
            ];
        }
        
        return $this;
    }
    
    
    /**
     * 添加注释方法
     * @param string               $name 方法名称
     * @param Argument[]           $arguments 方法参数
     * @param string[]|string|null $return 返回类型
     * @param bool                 $static 是否静态方法
     * @param string               $comment 方法说明
     * @return static
     */
    public function addDocMethod(string $name, array $arguments = [], array|string $return = null, bool $static = false, string $comment = '') : static
    {
        $methods = array_merge(array_change_key_case($this->docMethods, CASE_LOWER), array_change_key_case($this->methods, CASE_LOWER));
        if (!isset($methods[strtolower($name)])) {
            if ($return) {
                $return = implode('|', array_filter((array) $return));
            }
            
            $this->docMethods[$name] = [
                'static'    => $static,
                'arguments' => $arguments,
                'return'    => $return,
                'comment'   => $comment
            ];
        }
        
        return $this;
    }
    
    
    /**
     * 添加真实属性
     * @param string               $name 属性名称
     * @param string[]|string|null $type 属性类型
     * @param string|null          $value 属性默认值
     * @param bool                 $static 是否静态属性
     * @param string               $comment 属性注释
     * @param int                  $access 属性访问级别
     * @return static
     */
    public function addProperty(string $name, array|string $type = null, string $value = null, bool $static = false, string $comment = '', int $access = ReflectionProperty::IS_PUBLIC) : static
    {
        if (!isset($this->properties[$name])) {
            if ($type) {
                $type = implode('|', array_filter((array) $type));
            }
            
            $this->properties[$name] = [
                'type'    => $type,
                'value'   => $value,
                'static'  => $static,
                'comment' => $comment,
                'access'  => $access,
            ];
        }
        
        return $this;
    }
    
    
    /**
     * 添加真实方法
     * @param string               $name 方法名称
     * @param Argument[]           $arguments 方法参数
     * @param string|null          $code 方法代码
     * @param string[]|string|null $return 返回类型
     * @param bool                 $static 是否静态方法
     * @param string               $comment 方法说明
     * @param int                  $access 方法访问级别
     * @return static
     */
    public function addMethod(string $name, array $arguments = [], string $code = null, array|string $return = null, bool $static = false, string $comment = '', int $access = ReflectionProperty::IS_PUBLIC) : static
    {
        $methods = array_merge(array_change_key_case($this->docMethods, CASE_LOWER), array_change_key_case($this->methods, CASE_LOWER));
        if (!isset($methods[strtolower($name)])) {
            if ($return) {
                $return = implode('|', array_filter((array) $return));
            }
            
            $this->methods[$name] = [
                'static'    => $static,
                'arguments' => $arguments,
                'return'    => $return,
                'comment'   => $comment,
                'access'    => $access,
                'code'      => $code ?: ''
            ];
        }
        
        return $this;
    }
    
    
    /**
     * 生成
     * @throws ReflectionException
     */
    public function generate() : void
    {
        $this->reflection = new ReflectionClass($this->class);
        if (!$this->reflection->isInstantiable()) {
            return;
        }
        
        $this->output?->comment(sprintf("Loading %s '%s'", $this->name, $this->class));
        
        $this->handle();
        
        // 触发事件
        $this->dispatcher?->dispatch($this);
        
        $this->build();
    }
    
    
    /**
     * 处理
     */
    abstract protected function handle() : void;
    
    
    /**
     * 构建
     */
    protected function build() : void
    {
        $classname   = $this->reflection->getShortName();
        $originalDoc = $this->reflection->getDocComment();
        $context     = (new ContextFactory())->createFromReflector($this->reflection);
        $summary     = sprintf("Class %s", $this->class);
        $properties  = [];
        $methods     = [];
        $tags        = [];
        
        try {
            $phpdoc  = DocBlockFactory::createInstance()->create($this->reflection, $context);
            $summary = $phpdoc->getSummary();
            $tags    = $phpdoc->getTags();
            foreach ($tags as $key => $tag) {
                if ($tag instanceof Property || $tag instanceof PropertyRead || $tag instanceof PropertyWrite) {
                    // 覆盖原来的
                    if (($this->overwrite && array_key_exists($tag->getVariableName(), $this->docProperties)) || $this->reset) {
                        unset($tags[$key]);
                    } else {
                        $properties[] = $tag->getVariableName();
                    }
                } elseif ($tag instanceof Method) {
                    // 覆盖原来的
                    if (($this->overwrite && array_key_exists($tag->getMethodName(), $this->docMethods)) || $this->reset) {
                        unset($tags[$key]);
                    } else {
                        $methods[] = $tag->getMethodName();
                    }
                }
            }
        } catch (InvalidArgumentException $e) {
        }
        
        $fqsenResolver = new FqsenResolver();
        $tagFactory    = new StandardTagFactory($fqsenResolver);
        $tagFactory->addService(new DescriptionFactory($tagFactory));
        $tagFactory->addService(new TypeResolver($fqsenResolver));
        
        // 生成文档属性
        foreach ($this->docProperties as $name => $property) {
            if (in_array($name, $properties)) {
                continue;
            }
            
            if ($property['read'] && $property['write']) {
                $attr = 'property';
            } elseif ($property['write']) {
                $attr = 'property-write';
            } else {
                $attr = 'property-read';
            }
            $tags[] = $tagFactory->create(trim(sprintf('@%s %s $%s %s', $attr, $property['type'], $name, $property['comment'])));
        }
        
        // 生成文档方法
        foreach ($this->docMethods as $name => $method) {
            if (in_array($name, $methods)) {
                continue;
            }
            
            $arguments = implode(', ', $method['arguments']);
            $tags[]    = $tagFactory->create(sprintf("@method %s %s %s(%s) %s", $method['static'] ? 'static' : '', $method['return'], $name, $arguments, $method['comment']));
        }
        
        
        $tags       = $this->sortTags($tags);
        $phpdoc     = new DocBlock($summary, null, $tags, $context);
        $serializer = new Serializer();
        $docComment = $serializer->getDocComment($phpdoc);
        $filename   = $this->reflection->getFileName();
        $contents   = file_get_contents($filename);
        if ($originalDoc) {
            $contents = str_replace($originalDoc, $docComment, $contents);
        } else {
            $needle  = sprintf("class %s", $classname);
            $replace = sprintf("%s%sclass %s", $docComment, PHP_EOL, $classname);
            $pos     = strpos($contents, $needle);
            if (false !== $pos) {
                $contents = substr_replace($contents, $replace, $pos, strlen($needle));
            }
        }
        
        // 生成真实属性
        $propertyRange = [];
        foreach ($this->reflection->getProperties() as $property) {
            $propertyRange[] = $property->getName();
        }
        $properties = [];
        foreach ($this->properties as $name => $property) {
            if (in_array($name, $propertyRange)) {
                continue;
            }
            
            $properties[] = $this->createPropertyCode($name, $property);
        }
        if ($properties) {
            $contents = preg_replace_callback('/<\?.*?class\s[a-z0-9_]+.*?\{/is', function($match) use ($properties) {
                return $match[0] . PHP_EOL . implode(PHP_EOL . PHP_EOL, $properties) . PHP_EOL;
            }, $contents);
        }
        
        // 生成真实方法
        $methodRange = [];
        foreach ($this->reflection->getMethods() as $method) {
            $methodRange[] = $method->getName();
        }
        $methods = [];
        foreach ($this->methods as $name => $method) {
            if (in_array($name, $methodRange)) {
                continue;
            }
            $methods[] = $this->createMethodCode($name, $method);
        }
        if ($methods) {
            $pos = strrpos($contents, '}');
            if (false !== $pos) {
                $contents = substr_replace($contents, PHP_EOL . implode(PHP_EOL . PHP_EOL, $methods) . PHP_EOL, $pos, 0);
            }
        }
        
        if (file_put_contents($filename, $contents)) {
            $this->output?->info('Written new phpDocBlock to ' . $filename);
        }
    }
    
    
    /**
     * 生成真实属性代码
     * @param string                                                                       $name 属性名称
     * @param array{static: bool, type: string, access: int, value:mixed, comment: string} $property 属性配置
     * @return string
     */
    protected function createPropertyCode(string $name, array $property) : string
    {
        $access = match ($property['access']) {
            ReflectionProperty::IS_PROTECTED => 'protected',
            ReflectionProperty::IS_PRIVATE   => 'private',
            default                          => 'public',
        };
        
        return sprintf(<<<CODE
    /**
     * %s
     * @var %s
     */
    %s%s $%s%s;
CODE, $property['comment'], $property['type'], $access, $property['static'] ? ' static' : '', $name, $property['value'] ? ' = ' . $property['value'] : '');
    }
    
    
    /**
     * 生成真实方法代码
     * @param string                                                                                                 $name 方法名称
     * @param array{static: bool, arguments: Argument[], access: int, return: string, code: string, comment: string} $method 方法配置
     * @return string
     */
    protected function createMethodCode(string $name, array $method) : string
    {
        $access = match ($method['access']) {
            ReflectionProperty::IS_PROTECTED => 'protected',
            ReflectionProperty::IS_PRIVATE   => 'private',
            default                          => 'public',
        };
        
        return sprintf(<<<CODE
    /**
     * %s
     */
    %s%s function %s(%s)%s {
        %s
    }
CODE, $method['comment'], $access, $method['static'] ? ' static' : '', $name, implode(', ', $method['arguments']), $method['return'] ? ' : ' . $method['return'] : '', $method['code']);
    }
    
    
    /**
     * 排序
     * @param Tag[] $tags
     * @return Tag[]
     */
    protected function sortTags(array $tags) : array
    {
        $tagList    = [];
        $staticList = [];
        $sortNames  = ['', 'method', 'property-write', 'property-read', 'property'];
        foreach ($tags as $tag) {
            if (($tag instanceof Method && $tag->isStatic()) || !in_array($tag->getName(), $sortNames)) {
                $staticList[] = $tag;
            } else {
                $tagList[] = $tag;
            }
        }
        
        $sort = function(array $tags) use ($sortNames) {
            usort($tags, function(Tag $tag1, Tag $tag2) use ($sortNames) {
                $name1  = $tag1->getName();
                $name2  = $tag2->getName();
                $index1 = array_search($name1, $sortNames);
                $index2 = array_search($name2, $sortNames);
                
                if ($index1 == $index2) {
                    return strcmp($tag1->render(), $tag2->render());
                }
                
                if ($index1 > 0 || $index2 > 0) {
                    return $index1 - $index2;
                }
                
                return 0;
            });
            
            return $tags;
        };
        
        return array_merge($sort($staticList), $sort($tagList));
    }
}
