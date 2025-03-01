<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         0.1.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Bake\Shell\Task;

use Cake\Console\Shell;
use Cake\Core\Configure;
use Cake\Database\Exception;
use Cake\Database\Schema\Table;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use Cake\Utility\Text;
use DateTimeInterface;

/**
 * Task class for creating and updating fixtures files.
 *
 * @property \Bake\Shell\Task\BakeTemplateTask $BakeTemplate
 * @property \Bake\Shell\Task\ModelTask $Model
 */
class FixtureTask extends BakeTask
{
    /**
     * Tasks to be loaded by this Task
     *
     * @var array
     */
    public $tasks = [
        'Bake.Model',
        'Bake.BakeTemplate'
    ];

    /**
     * Get the file path.
     *
     * @return string
     */
    public function getPath()
    {
        $dir = 'Fixture/';
        $path = defined('TESTS') ? TESTS . $dir : ROOT . DS . 'tests' . DS . $dir;
        if (isset($this->plugin)) {
            $path = $this->_pluginPath($this->plugin) . 'tests/' . $dir;
        }

        return str_replace('/', DS, $path);
    }

    /**
     * Gets the option parser instance and configures it.
     *
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function getOptionParser()
    {
        $parser = parent::getOptionParser();

        $parser = $parser->setDescription(
            'Generate fixtures for use with the test suite. You can use `bake fixture all` to bake all fixtures.'
        )->addArgument('name', [
            'help' => 'Name of the fixture to bake (without the `Fixture` suffix). ' .
                'You can use Plugin.name to bake plugin fixtures.'
        ])->addOption('table', [
            'help' => 'The table name if it does not follow conventions.',
        ])->addOption('count', [
            'help' => 'When using generated data, the number of records to include in the fixture(s).',
            'short' => 'n',
            'default' => 1
        ])->addOption('schema', [
            'help' => 'Create a fixture that imports schema, instead of dumping a schema snapshot into the fixture.',
            'short' => 's',
            'boolean' => true
        ])->addOption('records', [
            'help' => 'Generate a fixture with records from the non-test database.' .
            ' Used with --count and --conditions to limit which records are added to the fixture.',
            'short' => 'r',
            'boolean' => true
        ])->addOption('conditions', [
            'help' => 'The SQL snippet to use when importing records.',
            'default' => '1=1',
        ])->addSubcommand('all', [
            'help' => 'Bake all fixture files for tables in the chosen connection.'
        ]);

        return $parser;
    }

    /**
     * Execution method always used for tasks
     * Handles dispatching to interactive, named, or all processes.
     *
     * @param string|null $name The name of the fixture to bake.
     * @return null|bool
     */
    public function main($name = null)
    {
        parent::main();
        $name = $this->_getName($name);

        if (empty($name)) {
            $this->out('Choose a fixture to bake from the following:');
            foreach ($this->Model->listUnskipped() as $table) {
                $this->out('- ' . $this->_camelize($table));
            }

            return true;
        }

        $table = null;
        if (isset($this->params['table'])) {
            $table = $this->params['table'];
        }
        $model = $this->_camelize($name);
        $this->bake($model, $table);
    }

    /**
     * Bake All the Fixtures at once. Will only bake fixtures for models that exist.
     *
     * @return void
     */
    public function all()
    {
        $tables = $this->Model->listUnskipped();

        foreach ($tables as $table) {
            $this->main($table);
        }
    }

    /**
     * Assembles and writes a Fixture file
     *
     * @param string $model Name of model to bake.
     * @param string|null $useTable Name of table to use.
     * @return string Baked fixture content
     * @throws \RuntimeException
     */
    public function bake($model, $useTable = null)
    {
        $table = $schema = $records = $import = $modelImport = null;

        if (!$useTable) {
            $useTable = Inflector::tableize($model);
        } elseif ($useTable !== Inflector::tableize($model)) {
            $table = $useTable;
        }

        $importBits = [];
        if (!empty($this->params['schema'])) {
            $modelImport = true;
            $importBits[] = "'table' => '{$useTable}'";
        }
        if (!empty($importBits) && $this->connection !== 'default') {
            $importBits[] = "'connection' => '{$this->connection}'";
        }
        if (!empty($importBits)) {
            $import = sprintf("[%s]", implode(', ', $importBits));
        }

        $connection = ConnectionManager::get($this->connection);
        if (!method_exists($connection, 'schemaCollection')) {
            throw new \RuntimeException(
                'Cannot generate fixtures for connections that do not implement schemaCollection()'
            );
        }
        $schemaCollection = $connection->schemaCollection();
        try {
            $data = $schemaCollection->describe($useTable);
        } catch (Exception $e) {
            $useTable = Inflector::underscore($model);
            $table = $useTable;
            $data = $schemaCollection->describe($useTable);
        }

        if ($modelImport === null) {
            $schema = $this->_generateSchema($data);
        }

        if (empty($this->params['records'])) {
            $recordCount = 1;
            if (isset($this->params['count'])) {
                $recordCount = $this->params['count'];
            }
            $records = $this->_makeRecordString($this->_generateRecords($data, $recordCount));
        }
        if (!empty($this->params['records'])) {
            $records = $this->_makeRecordString($this->_getRecordsFromTable($model, $useTable));
        }

        return $this->generateFixtureFile($model, compact('records', 'table', 'schema', 'import'));
    }

    /**
     * Generate the fixture file, and write to disk
     *
     * @param string $model name of the model being generated
     * @param array $otherVars Contents of the fixture file.
     * @return string Content saved into fixture file.
     */
    public function generateFixtureFile($model, array $otherVars)
    {
        $defaults = [
            'name' => $model,
            'table' => null,
            'schema' => null,
            'records' => null,
            'import' => null,
            'fields' => null,
            'namespace' => Configure::read('App.namespace')
        ];
        if ($this->plugin) {
            $defaults['namespace'] = $this->_pluginNamespace($this->plugin);
        }
        $vars = $otherVars + $defaults;

        $path = $this->getPath();
        $filename = $vars['name'] . 'Fixture.php';

        $this->BakeTemplate->set('model', $model);
        $this->BakeTemplate->set($vars);
        $content = $this->BakeTemplate->generate('tests/fixture');

        $this->out("\n" . sprintf('Baking test fixture for %s...', $model), 1, Shell::QUIET);
        $this->createFile($path . $filename, $content);
        $emptyFile = $path . 'empty';
        $this->_deleteEmptyFile($emptyFile);

        return $content;
    }

    /**
     * Generates a string representation of a schema.
     *
     * @param \Cake\Database\Schema\Table $table Table schema
     * @return string fields definitions
     */
    protected function _generateSchema(Table $table)
    {
        $cols = $indexes = $constraints = [];
        foreach ($table->columns() as $field) {
            $fieldData = $table->column($field);
            $properties = implode(', ', $this->_values($fieldData));
            $cols[] = "        '$field' => [$properties],";
        }
        foreach ($table->indexes() as $index) {
            $fieldData = $table->index($index);
            $properties = implode(', ', $this->_values($fieldData));
            $indexes[] = "            '$index' => [$properties],";
        }
        foreach ($table->constraints() as $index) {
            $fieldData = $table->constraint($index);
            $properties = implode(', ', $this->_values($fieldData));
            $constraints[] = "            '$index' => [$properties],";
        }
        $options = $this->_values($table->options());

        $content = implode("\n", $cols) . "\n";
        if (!empty($indexes)) {
            $content .= "        '_indexes' => [\n" . implode("\n", $indexes) . "\n        ],\n";
        }
        if (!empty($constraints)) {
            $content .= "        '_constraints' => [\n" . implode("\n", $constraints) . "\n        ],\n";
        }
        if (!empty($options)) {
            foreach ($options as &$option) {
                $option = '            ' . $option;
            }
            $content .= "        '_options' => [\n" . implode(",\n", $options) . "\n        ],\n";
        }

        return "[\n$content    ]";
    }

    /**
     * Formats Schema columns from Model Object
     *
     * @param array $values options keys(type, null, default, key, length, extra)
     * @return array Formatted values
     */
    protected function _values($values)
    {
        $vals = [];
        if (!is_array($values)) {
            return $vals;
        }
        foreach ($values as $key => $val) {
            if (is_array($val)) {
                $vals[] = "'{$key}' => [" . implode(", ", $this->_values($val)) . "]";
            } else {
                $val = var_export($val, true);
                if ($val === 'NULL') {
                    $val = 'null';
                }
                if (!is_numeric($key)) {
                    $vals[] = "'{$key}' => {$val}";
                } else {
                    $vals[] = "{$val}";
                }
            }
        }

        return $vals;
    }

    /**
     * Generate String representation of Records
     *
     * @param \Cake\Database\Schema\Table $table Table schema array
     * @param int $recordCount The number of records to generate.
     * @return array Array of records to use in the fixture.
     */
    protected function _generateRecords(Table $table, $recordCount = 1)
    {
        $records = [];
        for ($i = 0; $i < $recordCount; $i++) {
            $record = [];
            foreach ($table->columns() as $field) {
                $fieldInfo = $table->column($field);
                $insert = '';
                switch ($fieldInfo['type']) {
                    case 'decimal':
                        $insert = $i + 1.5;
                        break;
                    case 'biginteger':
                    case 'integer':
                    case 'float':
                    case 'smallinteger':
                    case 'tinyinteger':
                        $insert = $i + 1;
                        break;
                    case 'string':
                    case 'binary':
                        $isPrimary = in_array($field, $table->primaryKey());
                        if ($isPrimary) {
                            $insert = Text::uuid();
                        } else {
                            $insert = "Lorem ipsum dolor sit amet";
                            if (!empty($fieldInfo['length'])) {
                                $insert = substr($insert, 0, (int)$fieldInfo['length'] - 2);
                            }
                        }
                        break;
                    case 'timestamp':
                        $insert = time();
                        break;
                    case 'datetime':
                        $insert = date('Y-m-d H:i:s');
                        break;
                    case 'date':
                        $insert = date('Y-m-d');
                        break;
                    case 'time':
                        $insert = date('H:i:s');
                        break;
                    case 'boolean':
                        $insert = 1;
                        break;
                    case 'text':
                        $insert = "Lorem ipsum dolor sit amet, aliquet feugiat.";
                        $insert .= " Convallis morbi fringilla gravida,";
                        $insert .= " phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin";
                        $insert .= " venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla";
                        $insert .= " vestibulum massa neque ut et, id hendrerit sit,";
                        $insert .= " feugiat in taciti enim proin nibh, tempor dignissim, rhoncus";
                        $insert .= " duis vestibulum nunc mattis convallis.";
                        break;
                    case 'uuid':
                        $insert = Text::uuid();
                        break;
                }
                $record[$field] = $insert;
            }
            $records[] = $record;
        }

        return $records;
    }

    /**
     * Convert a $records array into a string.
     *
     * @param array $records Array of records to be converted to string
     * @return string A string value of the $records array.
     */
    protected function _makeRecordString($records)
    {
        $out = "[\n";
        foreach ($records as $record) {
            $values = [];
            foreach ($record as $field => $value) {
                if ($value instanceof DateTimeInterface) {
                    $value = $value->format('Y-m-d H:i:s');
                }
                $val = var_export($value, true);
                if ($val === 'NULL') {
                    $val = 'null';
                }
                $values[] = "            '$field' => $val";
            }
            $out .= "        [\n";
            $out .= implode(",\n", $values);
            $out .= "\n        ],\n";
        }
        $out .= "    ]";

        return $out;
    }

    /**
     * Interact with the user to get a custom SQL condition and use that to extract data
     * to build a fixture.
     *
     * @param string $modelName name of the model to take records from.
     * @param string|null $useTable Name of table to use.
     * @return array Array of records.
     */
    protected function _getRecordsFromTable($modelName, $useTable = null)
    {
        $recordCount = (isset($this->params['count']) ? $this->params['count'] : 10);
        $conditions = (isset($this->params['conditions']) ? $this->params['conditions'] : '1=1');
        if (TableRegistry::exists($modelName)) {
            $model = TableRegistry::get($modelName);
        } else {
            $model = TableRegistry::get($modelName, [
                'table' => $useTable,
                'connection' => ConnectionManager::get($this->connection)
            ]);
        }
        $records = $model->find('all')
            ->where($conditions)
            ->limit($recordCount)
            ->enableHydration(false);

        return $records;
    }
}
