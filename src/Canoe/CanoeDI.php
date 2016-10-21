<?php
/**
 * Created by PhpStorm.
 * User: panzd
 * Date: 10/10/2016
 * Time: 11:51 AM
 */

namespace Canoe;

/**
 * Class Container
 * @package Canoe
 */
class CanoeDI
{
    private static $definitions = array();
    private static $beans = array();

    /**
     * $config is a array with schema like follow:
     *
     * [
     *  'definitions' => [
     *      'id1' => function() {},
     *      'id2' => 'ClassName1',
     *      'ClassName2'
     *  ],
     *  'beans' => [
     *      'id3' => $value
     *  ]
     * ]
     *
     * @param array $config
     */
    public static function loadConfig(array $config)
    {
        if (isset($config['beans'])) {
            foreach ($config['beans'] as $key => $value) {
                if (!is_numeric($key)) {
                    self::set($key, $value);
                } else {
                    throw new \UnexpectedValueException('invalid key for bean');
                }
            }
        }

        if (isset($config['definitions'])) {
            foreach ($config['definitions'] as $key => $definition) {
                self::registerDefinition($definition, is_numeric($key) ? null : $key);
            }
        }
    }
    /**
     * @param string|callable $definition
     * @param string|null     $id
     */
    public static function registerDefinition($definition, $id = null)
    {
        if (empty($definition)) {
            throw new \InvalidArgumentException('definition cannot be empty');
        }

        if (is_callable($definition)) {
            self::registerCallable($definition, $id);
        } elseif (is_string($definition) && class_exists($definition)) {
            self::registerClass($definition, $id);
        } else {
            throw new \InvalidArgumentException('definition cannot be empty');
        }
    }

    /**
     * @param string $id
     * @param mixed  $value
     */
    public static function set($id, $value)
    {
        if (empty($id) || !is_string($id)) {
            throw new \InvalidArgumentException('invalid id');
        }

        if (class_exists($id) && !($value instanceof $id)) {
            throw new \InvalidArgumentException('bean is not a instance of $id');
        }

        if (is_object($value)) {
            self::autoRegisterBean($value);
        }

        self::$beans[$id] = $value;
    }

    /**
     * @param string $id
     * @return mixed
     */
    public static function get($id)
    {
        if (isset(self::$beans[$id])) {
            return self::$beans[$id];
        } else {
            $bean = null;
            if (isset(self::$definitions[$id])) {
                $bean = self::createFromDefinition(self::$definitions[$id]);
                self::set($id, $bean);
            } elseif (class_exists($id)) {
                $bean = self::createFromClass($id);
                self::set($id, $bean);
            }

            return $bean;
        }
    }

    /**
     * @param callable $callback
     * @param string $id
     */
    private static function registerCallable(callable $callback, $id)
    {
        if (empty($id) || !is_string($id)) {
            throw new \InvalidArgumentException("invalid id");
        }

        self::$definitions[$id] = $callback;
    }

    /**
     * @param string $class
     * @param string|null $id
     */
    private static function registerClass($class, $id = null)
    {
        if (!empty($id)) {
            if (!is_string($id)) {
                throw new \InvalidArgumentException("invalid id");
            }

            if (class_exists($id) && $id != $class && !is_subclass_of($class, $id)) {
                throw new \InvalidArgumentException("$class is not a subclass of $id");
            }
        }

        self::autoRegisterClass($class);

        if (!empty($id)) {
            self::$definitions[$id] = $class;
        }
    }


    private static function autoRegisterClass($class)
    {
        $parentClass = $class;
        while ($parentClass != null) {
            if (!isset(self::$definitions[$parentClass])) {
                self::$definitions[$parentClass] = $class;
            }

            $parentClass = get_parent_class($parentClass);
        }

        foreach (class_implements($class) as $interface) {
            if (!isset(self::$definitions[$interface])) {
                self::$definitions[$interface] = $class;
            }
        }
    }

    private static function autoRegisterBean($bean)
    {
        if (!is_object($bean)) {
            return;
        }

        $class = get_class($bean);
        $parentClass = $class;
        while ($parentClass != null) {
            if (!isset(self::$beans[$parentClass])) {
                self::$beans[$parentClass] = $bean;
            }

            $parentClass = get_parent_class($parentClass);
        }

        foreach (class_implements($class) as $interface) {
            if (!isset(self::$beans[$interface])) {
                self::$beans[$interface] = $bean;
            }
        }
    }

    private static function createFromDefinition($definition)
    {
        if (is_callable($definition)) {
            return $definition();
        }

        return self::createFromClass($definition);
    }

    private static function createFromClass($className)
    {
        $class = new \ReflectionClass($className);
        $constructor = $class->getConstructor();

        if ($constructor == null) {
            return $class->newInstance();
        }

        $formalParameters = $constructor->getParameters();
        $actualParameters = [];

        foreach ($formalParameters as $parameter) {
            if ($parameter->isOptional()) {
                break;
            }

            $parameterName = $parameter->getName();
            $actualParameter = self::get($parameterName);
            if ($actualParameter == null && !empty($parameterClass = $parameter->getClass())) {
                $actualParameter = self::get($parameterClass->getName());
            }

            if (empty($actualParameter)) {
                throw new \InvalidArgumentException("create $class instance failed: can't find a bean with id [$parameterName]");
            }

            $actualParameters[] = $actualParameter;
        }

        return $class->newInstanceArgs($actualParameters);
    }

}