<?php

/*
 * UserFrosting Form Generator
 *
 * @link      https://github.com/lcharette/UF_FormGenerator
 * @copyright Copyright (c) 2020 Louis Charette
 * @license   https://github.com/lcharette/UF_FormGenerator/blob/master/LICENSE (MIT License)
 */

namespace UserFrosting\Sprinkle\FormGenerator;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use UserFrosting\Fortress\RequestSchema\RequestSchemaRepository;
use UserFrosting\Sprinkle\FormGenerator\Element\InputInterface;
use UserFrosting\Sprinkle\FormGenerator\Element\Text;
use UserFrosting\Sprinkle\FormGenerator\Exception\InvalidClassException;

/**
 * Form Class.
 *
 * The FormGenerator class, which is used to return the `form` part from a Fortress
 * schema for html form generator in Twig.
 */
class Form
{
    /**
     * @var RequestSchemaRepository The form fields definition
     */
    protected $schema;

    /**
     * @var array<string,string> The form values
     */
    protected $data = [];

    /**
     * @var string Use this to wrap form fields in top-level array
     */
    protected $formNamespace = '';

    /**
     * Class constructor.
     *
     * @param RequestSchemaRepository                          $schema
     * @param array<string>|Collection<mixed>|Model|Repository $data   (default: [])
     */
    public function __construct(RequestSchemaRepository $schema, $data = [])
    {
        $this->setSchema($schema);
        $this->setData($data);
    }

    /**
     * Set the form current values.
     *
     * @param array<string>|Collection<mixed>|Model|Repository $data The form values
     */
    public function setData($data): void
    {
        if ($data instanceof Collection || $data instanceof Model) {
            $this->data = $data->toArray();
        } elseif ($data instanceof Repository) {
            $this->data = $data->all();
        } elseif (is_array($data)) {
            $this->data = $data;
        } else {
            throw new InvalidArgumentException('Data must be an array, a Collection, a Model or a Repository');
        }
    }

    /**
     * Set the schema for this validator.
     *
     * @param RequestSchemaRepository $schema A RequestSchemaRepository object, containing the form definition.
     */
    public function setSchema(RequestSchemaRepository $schema): void
    {
        $this->schema = $schema;
    }

    /**
     * Use to define the value of a form input when `setData` is already set.
     *
     * @param string $inputName
     * @param string $value
     */
    public function setValue(string $inputName, string $value): void
    {
        $this->data[$inputName] = $value;
    }

    /**
     * Function used to overwrite the input argument from a schema file
     * Can also be used to overwrite an argument hardcoded in the Twig file.
     * Use `setCustomFormData` to set any other tag.
     *
     * @param string $inputName The input name where the argument will be added
     * @param string $property  The argument name. Example "data-color"
     * @param string $value     The value of the argument
     */
    public function setInputArgument(string $inputName, string $property, string $value): void
    {
        if ($this->schema->has($inputName)) {
            // Get the element and force set the property
            $element = $this->schema->get($inputName);
            $element['form'][$property] = $value;

            // Push back the modifyed element in the schema
            $this->schema->set($inputName, $element);
        }
    }

    /**
     * Function used to set options of a select element. Shortcut for using
     * `setInputArgument` and `setValue`.
     *
     * @param string               $inputName The select name to add options to
     * @param array<string,string> $data      An array of `value => label` options
     * @param string               $selected  The selected key
     */
    public function setOptions(string $inputName, $data = [], ?string $selected = null): void
    {
        // Set opdations
        $this->setInputArgument($inputName, 'options', $data);

        // Set the value
        if (!is_null($selected)) {
            $this->setValue($inputName, $selected);
        }
    }

    /**
     * Function to set the form namespace.
     * Use the form namespace to wrap the fields name in a top level array.
     * Useful when using multiple schemas at once or if the names are using dot syntaxt.
     * See : http://stackoverflow.com/a/20365198/445757.
     *
     * @param string $namespace
     */
    public function setFormNamespace(string $namespace): void
    {
        $this->formNamespace = $namespace;
    }

    /**
     * Generate an array contining all nececerry value to generate a form
     * with Twig.
     *
     * @return array<string,string> The form fields data
     */
    public function generate(): array
    {
        $form = collect([]);

        // Loop all the the fields in the schema
        foreach ($this->schema->all() as $name => $input) {

            // Skip if it doesn't have a `form` definition
            if (!isset($input['form'])) {
                continue;
            }

            // Alias the schema input
            $element = $input['form'];

            // Get the value from the data
            $value = $this->getValueForName($name);

            // Add the namespace to the name if it's defined
            $name = $this->getNamespacedName($name);

            // If element doesn't have a type, inject default (text)
            if (!isset($element['type'])) {
                $element['type'] = 'text';
            }

            // Get the class FQN
            $elementClass = $this->getElementClass($element['type']);

            // Create the actual class
            $class = new $elementClass($name, $element, $value);

            // Make sure we have an instance of InputInterface.
            // An error will be thrown otherwise
            if (!$class instanceof InputInterface) {
                throw new InvalidClassException('Element class needs to implement InputInterface');
            }

            // Put parsed data from the InputInterface to `$form`
            // Collection put is used here, because of the namespace, which will put it at the right place
            $form->put($name, $class->parse());
        }

        return $form->toArray();
    }

    /**
     * Gets the defined value for a specific field name.
     *
     * @param string $name
     *
     * @return string|null
     */
    protected function getValueForName(string $name)
    {
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }
    }

    /**
     * Get the name of the field, wrapped by the global form namespace.
     *
     * @param string $name
     *
     * @return string
     */
    protected function getNamespacedName(string $name): string
    {
        if ($this->formNamespace !== '') {
            return $this->formNamespace . '[' . $name . ']';
        }

        return $name;
    }

    /**
     * Gets the element class fully qualified name from it's type.
     *
     * @param string $type
     *
     * @return string
     */
    protected function getElementClass(string $type): string
    {
        // Get the element class and make sure it exist
        // @todo : Allow for custom classes, or use a `switch` and/or config file to avoid hard coding this class
        $typeClass = 'UserFrosting\\Sprinkle\\FormGenerator\\Element\\' . Str::studly($type);

        // If class doesn't esist, default to Text element
        if (!class_exists($typeClass)) {
            return Text::class;
        }

        return $typeClass;
    }
}
