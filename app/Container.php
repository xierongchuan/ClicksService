<?php

declare(strict_types=1);

namespace App;

use App\Exceptions\ContainerException;
use Psr\Container\ContainerInterface;
use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionNamedType;

/**
 * Легковесный DI контейнер.
 */
class Container implements ContainerInterface
{
    /**
     * @var array Хранилище для зарегистрированных зависимостей.
     * В формате: ['имя_сервиса' => ['concrete' => 'реализация', 'singleton' => bool]]
     */
    protected array $bindings = [];

    /**
     * @var array Хранилище для уже созданных экземпляров одиночек.
     */
    protected array $instances = [];

    /**
     * Регистрирует зависимость в контейнере.
     *
     * @param string $abstract Абстракция интерфейс/имя класса/ключ.
     * @param string|Closure|null $concrete Конкретная реализация или фабрика.
     * @param bool $singleton Является ли зависимость одиночкой.
     */
    public function bind(string $abstract, string|Closure|null $concrete = null, bool $singleton = false): void
    {
        // Если конкретная реализация не указана, считаем, что абстракция является реализацией.
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'singleton' => $singleton
        ];
    }

    /**
     * Метод для регистрации одиночки.
     *
     * @param string $abstract
     * @param string|Closure|null $concrete
     */
    public function singleton(string $abstract, string|Closure|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Разрешает (создает и возвращает) зависимость из контейнера.
     *
     * @param string $id Идентификатор/абстракция зависимости.
     * @return mixed
     * @throws ContainerException
     */
    public function get(string $id): mixed
    {
        // Проверяем, есть ли уже готовый экземпляр-одиночка
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // Ищем "рецепт" для создания в биндингах
        $binding = $this->bindings[$id] ?? ['concrete' => $id, 'singleton' => false];
        $concrete = $binding['concrete'];

        // Создаем объект
        // Если рецепт это фабрика/замыкание то просто вызываем ее,
        // иначе строим объект с помощью рефлексии
        if ($concrete instanceof Closure) {
            $object = $concrete($this);
        } else {
            $object = $this->build($concrete);
        }

        // Eсли это одиночка сохраняем экземпляр для будущих запросов
        if ($binding['singleton']) {
            $this->instances[$id] = $object;
        }

        return $object;
    }

    /**
     * Проверяет зарегистрирована ли зависимость.
     *
     * @param string $id Идентификатор/абстракция зависимости.
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]);
    }

    /**
    * Основной метод для построения объекта и его зависимостей через рефлексию.
    *
    * @param string $concrete Имя конкретного класса.
    * @return object
    * @throws ContainerException
    */
    protected function build(string $concrete): object
    {
        try {
            $reflector = new ReflectionClass($concrete);
        } catch (ReflectionException $e) {
            throw new ContainerException("Класс '{$concrete}' не существует.", 0, $e);
        }

        // Проверяем можно ли создать экземпляр этого класса.
        if (!$reflector->isInstantiable()) {
            throw new ContainerException("Класс '{$concrete}' не может быть инстанциирован.");
        }

        $constructor = $reflector->getConstructor();

        // Если конструктора/зависимостей нет создаем объект.
        if ($constructor === null) {
            return new $concrete();
        }

        $parameters = $constructor->getParameters();
        $dependencies = $this->resolveParameters($parameters);

        // Создаем экземпляр класса передавая разрешенные зависимости в конструктор.
        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Разрешает зависимости для параметров конструктора (TypeHint)
     *
     * @param ReflectionParameter[] $parameters
     * @return array
     */
    protected function resolveParameters(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            // Если у параметра нет type-hint или это встроенный тип string/int мы не можем его разрешить.
            if ($type === null || ($type instanceof ReflectionNamedType && $type->isBuiltin())) {
                // Проверяем естьли у параметра значение по умолчанию.
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                    continue;
                }
                throw new ContainerException(
                    "Невозможно разрешить зависимость '{$parameter->getName()}' без type-hint."
                );
            }

            // Получаем имя класса/интерфейса из TypeHint
            $dependencyClass = $type instanceof ReflectionNamedType ? $type->getName() : (string) $type;

            // Рекурсивный вызов. Просим контейнер разрешить зависимость
            $dependencies[] = $this->get($dependencyClass);
        }

        return $dependencies;
    }
}
