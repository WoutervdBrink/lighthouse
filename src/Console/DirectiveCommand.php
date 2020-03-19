<?php

namespace Nuwave\Lighthouse\Console;

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
        $stub = parent::buildClass($name);

        if ($this->option('validator')) {
            // A validator will not be implementing any interface.
            return $stub;
        }

        if ($this->option('type')) {
            $this->askForInterfaces($stub, ['TypeManipulator', 'TypeMiddleware', 'TypeResolver', 'TypeExtensionManipulator']);
        }

        if ($this->option('field')) {
            $this->askForInterfaces($stub, ['FieldResolver', 'FieldMiddleware', 'FieldManipulator']);
        }

        if ($this->option('argument')) {
            $this->askForInterfaces($stub, ['ArgTransformerDirective', 'ArgBuilderDirective', 'ArgResolver', 'ArgManipulator']);
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
     * @return void
     */
    protected function insertInterface(string &$stub, string $interface)
    {
        $stub = str_replace(
            '{{ imports }}',
            'use Nuwave\\Support\\Contracts\\'.$interface."\n" . '{{ imports }}',
            $stub
        );

        $stub = str_replace(
            '{{ implements }}',
            $interface.', {{ implements }}',
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

        // If no interfaces were enabled, we are left with a {{ implements }}.
        $stub = str_replace('{{ implements }}', '', $stub);

        // Whether or not imports were made, the {{ imports }} is still there.
        $stub = str_replace('{{ imports }}', '', $stub);
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        if ($this->option('validator')) {
            return __DIR__.'/stubs/validator.stub';
        }

        return __DIR__.'/stubs/directive.stub';
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
            ['validator', null, InputOption::VALUE_NONE, 'Create a directive which validates a field.']
        ];
    }
}
