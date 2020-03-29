<?php

namespace Nuwave\Lighthouse\Console;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class DirectiveCommand extends LighthouseGeneratorCommand
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'lighthouse:directive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a class for a directive.';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Directive';

    /**
     * The imports required by the various interfaces, if any.
     *
     * @var Collection
     */
    protected $imports;

    protected function getNameInput(): string
    {
        return ucfirst(trim($this->argument('name'))).'Directive';
    }

    protected function namespaceConfigKey(): string
    {
        return 'directives';
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     * @return string
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function buildClass($name)
    {
        $this->imports = new Collection();

        $stub = parent::buildClass($name);

        if ($this->option('type')) {
            $this->askForInterfaces($stub, ['TypeManipulator', 'TypeMiddleware', 'TypeResolver', 'TypeExtensionManipulator']);
        }

        if ($this->option('field')) {
            $this->askForInterfaces($stub, ['FieldResolver', 'FieldMiddleware', 'FieldManipulator']);
        }

        if ($this->option('argument')) {
            // https://lighthouse-php.com/4.10/custom-directives/argument-directives.html
            // Arg directives always either implement ArgDirective or ArgDirectiveForArray.
            if ($this->confirm('Will your argument directive apply to a list of items?')) {
                $this->insertInterface($stub, 'ArgDirectiveForArray', false);
            } else {
                $this->insertInterface($stub, 'ArgDirective', false);
            }

            $this->askForInterfaces($stub, ['ArgTransformerDirective', 'ArgBuilderDirective', 'ArgResolver', 'ArgManipulator']);
        }

        if ($this->imports->isNotEmpty()) {
            $stub = str_replace(
                '{{ imports }}',
                $this->imports
                    ->filter()
                    ->unique()
                    ->implode("\n"),
                $stub
            );
        }

        $this->cleanup($stub);

        return $stub;
    }

    /**
     * Ask the user if the directive should implement any of the given
     * interfaces.
     *
     * @param string $stub
     * @param array $interfaces
     * @return void
     */
    protected function askForInterfaces(string &$stub, array $interfaces)
    {
        foreach ($interfaces as $interface) {
            if ($this->confirm('Should the directive implement the '.$interface.' middleware?')) {
                $this->insertInterface($stub, $interface);
            }
        }
    }

    /**
     * Insert an interface into a directive stub. Adds the use statement to the
     * top of the stub and the interface itself in the implements statement.
     *
     * @param string $stub
     * @param string $interface
     * @param bool $withMethods
     * @return void
     */
    protected function insertInterface(string &$stub, string $interface, bool $withMethods = true)
    {
        $stub = str_replace(
            '{{ imports }}',
            'use Nuwave\\Lighthouse\\Support\\Contracts\\'.$interface.";\n{{ imports }}",
            $stub
        );

        $stub = str_replace(
            '{{ implements }}',
            $interface.', {{ implements }}',
            $stub
        );

        if (! $withMethods) {
            // No need to implement methods for this interface, so return early.
            return;
        }

        $imports = $this->files->get($this->getStubForInterfaceImports($interface));
        $imports = explode("\n", $imports);

        $this->imports->push(...$imports);

        $stub = str_replace(
            '{{ methods }}',
            $this->files->get($this->getStubForInterfaceMethods($interface))."\n\n{{ methods }}",
            $stub
        );
    }

    /**
     * Remove any leftover template helper strings in the stub.
     *
     * @param string $stub
     * @return void
     */
    protected function cleanup(string &$stub)
    {
        // If one or more interfaces are enabled, we are left with ", {{ implements }}".
        $stub = str_replace(', {{ implements }}', '', $stub);

        // If no interfaces were enabled, we are left with "implements {{ implements }}".
        $stub = str_replace('implements {{ implements }}', '', $stub);

        // When no imports were made, the {{ imports }} is still there.
        $stub = str_replace("{{ imports }}\n", '', $stub);

        // Whether or not methods were implemented, the {{ methods }} is still there.
        $stub = str_replace("\n\n{{ methods }}", '', $stub);
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return __DIR__.'/stubs/directive.stub';
    }

    /**
     * Get the stub file for the methods required by an interface.
     *
     * @param string $interface
     * @return string
     */
    protected function getStubForInterfaceMethods(string $interface): string
    {
        return __DIR__.'/stubs/directives/'.Str::snake($interface).'.stub';
    }

    /**
     * Get the stub file for the imports required by an interface.
     *
     * @param string $interface
     * @return string
     */
    protected function getStubForInterfaceImports(string $interface): string
    {
        return __DIR__.'/stubs/directives/'.Str::snake($interface).'_imports.stub';
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['type', null, InputOption::VALUE_NONE, 'Create a directive that can be applied to types.'],
            ['field', null, InputOption::VALUE_NONE, 'Create a directive that can be applied to fields.'],
            ['argument', null, InputOption::VALUE_NONE, 'Create a directive that can be applied to arguments.'],
        ];
    }
}
