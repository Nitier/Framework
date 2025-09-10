<?php

declare(strict_types=1);

namespace Framework;

use DI\Container;
use DI\ContainerBuilder;
use Framework\General\Path;
use Framework\General\Environment;

class Kernel
{
    /** @var Container The dependency injection container instance */
    private Container $container;
    /** @var array<mixed> */
    private array $settings = [];

    /**
     * Kernel constructor.
     * Initializes the kernel by setting up paths and building the container.
     */
    public function __construct()
    {
        $kernelPath = new Path(dirname(__DIR__));
        $this->loadDefinitions($kernelPath->get('config'));
    }
    public function loadApplication(string $rootPath): self
    {
        $path = new Path($rootPath);
        $this->loadDefinitions($path->get('config'));
        $this->settings[Path::class] = $path;
        $this->buildContainer($this->settings);
        $this->get(Environment::class);
        return $this;
    }

    /**
     * Loads and processes configuration files from the config directory.
     * @param string $path path to definitions directory
     * @return void
     */
    private function loadDefinitions(string $path): void
    {
        // Load the configuration directory path
        if (!is_dir($path)) {
            return;
        }
        // Load all .php files in the config directory
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $this->settings = array_replace_recursive(
                $this->settings,
                (array) require $file->getRealPath()
            );
        }
    }

    /**
     * Builds the dependency injection container with the provided definitions.
     * Registers the Kernel instance as a singleton in the container.
     * @param array<mixed> $definitions Container service definitions
     * @return void
     */
    protected function buildContainer(array $definitions = []): void
    {
        // Register the Kernel as a singleton
        $definitions[Kernel::class] = $this;
        // Build the container
        $this->container = (new ContainerBuilder())
            ->addDefinitions($definitions)
            ->build();
    }

    /**
     * Returns an entry of the container by its name.
     *
     * @param string $key Identifier
     * @param mixed $default Default value to return if the key does not exist
     * @return mixed The instance or value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Check if the key exists in the container
        if (!$this->container->has($key)) {
            return $default;
        }
        // Get the value directly from the container
        return $this->container->get($key);
    }

    /**
     * Sets a value or service in the container.
     *
     * @param string $key Identifier
     * @param mixed $value Instance or value
     * @throws \InvalidArgumentException If the key is invalid
     */
    public function set(string $key, mixed $value): void
    {
        // Set the value in the container
        $this->container->set($key, $value);
    }


    /**
     * Returns true if the container can return an entry for the given identifier. Returns false otherwise.
     *
     * @param string $key Service identifier to check
     * @return bool True if the service exists, false otherwise
     */
    public function has(string $key): bool
    {
        // Check service existence for simple key
        return $this->container->has($key);
    }
}
