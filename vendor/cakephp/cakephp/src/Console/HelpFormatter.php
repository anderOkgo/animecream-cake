<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         2.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Console;

use Cake\Console\Exception\ConsoleException;
use Cake\Utility\Text;
use SimpleXMLElement;

/**
 * HelpFormatter formats help for console shells. Can format to either
 * text or XML formats. Uses ConsoleOptionParser methods to generate help.
 *
 * Generally not directly used. Using $parser->help($command, 'xml'); is usually
 * how you would access help. Or via the `--help=xml` option on the command line.
 *
 * Xml output is useful for integration with other tools like IDE's or other build tools.
 */
class HelpFormatter
{

    /**
     * The maximum number of arguments shown when generating usage.
     *
     * @var int
     */
    protected $_maxArgs = 6;

    /**
     * The maximum number of options shown when generating usage.
     *
     * @var int
     */
    protected $_maxOptions = 6;

    /**
     * Option parser.
     *
     * @var \Cake\Console\ConsoleOptionParser
     */
    protected $_parser;

    /**
     * Alias to display in the output.
     *
     * @var string
     */
    protected $_alias = 'cake';

    /**
     * Build the help formatter for an OptionParser
     *
     * @param \Cake\Console\ConsoleOptionParser $parser The option parser help is being generated for.
     */
    public function __construct(ConsoleOptionParser $parser)
    {
        $this->_parser = $parser;
    }

    /**
     * Set the alias
     *
     * @param string $alias The alias
     * @return void
     * @throws \Cake\Console\Exception\ConsoleException When alias is not a string.
     */
    public function setAlias($alias)
    {
        if (is_string($alias)) {
            $this->_alias = $alias;
        } else {
            throw new ConsoleException('Alias must be of type string.');
        }
    }

    /**
     * Get the help as formatted text suitable for output on the command line.
     *
     * @param int $width The width of the help output.
     * @return string
     */
    public function text($width = 72)
    {
        $parser = $this->_parser;
        $out = [];
        $description = $parser->getDescription();
        if (!empty($description)) {
            $out[] = Text::wrap($description, $width);
            $out[] = '';
        }
        $out[] = '<info>Usage:</info>';
        $out[] = $this->_generateUsage();
        $out[] = '';
        $subcommands = $parser->subcommands();
        if (!empty($subcommands)) {
            $out[] = '<info>Subcommands:</info>';
            $out[] = '';
            $max = $this->_getMaxLength($subcommands) + 2;
            foreach ($subcommands as $command) {
                $out[] = Text::wrapBlock($command->help($max), [
                    'width' => $width,
                    'indent' => str_repeat(' ', $max),
                    'indentAt' => 1
                ]);
            }
            $out[] = '';
            $out[] = sprintf('To see help on a subcommand use <info>`' . $this->_alias . ' %s [subcommand] --help`</info>', $parser->getCommand());
            $out[] = '';
        }

        $options = $parser->options();
        if (!empty($options)) {
            $max = $this->_getMaxLength($options) + 8;
            $out[] = '<info>Options:</info>';
            $out[] = '';
            foreach ($options as $option) {
                $out[] = Text::wrapBlock($option->help($max), [
                    'width' => $width,
                    'indent' => str_repeat(' ', $max),
                    'indentAt' => 1
                ]);
            }
            $out[] = '';
        }

        $arguments = $parser->arguments();
        if (!empty($arguments)) {
            $max = $this->_getMaxLength($arguments) + 2;
            $out[] = '<info>Arguments:</info>';
            $out[] = '';
            foreach ($arguments as $argument) {
                $out[] = Text::wrapBlock($argument->help($max), [
                    'width' => $width,
                    'indent' => str_repeat(' ', $max),
                    'indentAt' => 1
                ]);
            }
            $out[] = '';
        }
        $epilog = $parser->getEpilog();
        if (!empty($epilog)) {
            $out[] = Text::wrap($epilog, $width);
            $out[] = '';
        }

        return implode("\n", $out);
    }

    /**
     * Generate the usage for a shell based on its arguments and options.
     * Usage strings favor short options over the long ones. and optional args will
     * be indicated with []
     *
     * @return string
     */
    protected function _generateUsage()
    {
        $usage = [$this->_alias . ' ' . $this->_parser->getCommand()];
        $subcommands = $this->_parser->subcommands();
        if (!empty($subcommands)) {
            $usage[] = '[subcommand]';
        }
        $options = [];
        foreach ($this->_parser->options() as $option) {
            $options[] = $option->usage();
        }
        if (count($options) > $this->_maxOptions) {
            $options = ['[options]'];
        }
        $usage = array_merge($usage, $options);
        $args = [];
        foreach ($this->_parser->arguments() as $argument) {
            $args[] = $argument->usage();
        }
        if (count($args) > $this->_maxArgs) {
            $args = ['[arguments]'];
        }
        $usage = array_merge($usage, $args);

        return implode(' ', $usage);
    }

    /**
     * Iterate over a collection and find the longest named thing.
     *
     * @param array $collection The collection to find a max length of.
     * @return int
     */
    protected function _getMaxLength($collection)
    {
        $max = 0;
        foreach ($collection as $item) {
            $max = (strlen($item->name()) > $max) ? strlen($item->name()) : $max;
        }

        return $max;
    }

    /**
     * Get the help as an xml string.
     *
     * @param bool $string Return the SimpleXml object or a string. Defaults to true.
     * @return string|\SimpleXMLElement See $string
     */
    public function xml($string = true)
    {
        $parser = $this->_parser;
        $xml = new SimpleXMLElement('<shell></shell>');
        $xml->addChild('command', $parser->getCommand());
        $xml->addChild('description', $parser->getDescription());

        $subcommands = $xml->addChild('subcommands');
        foreach ($parser->subcommands() as $command) {
            $command->xml($subcommands);
        }
        $options = $xml->addChild('options');
        foreach ($parser->options() as $option) {
            $option->xml($options);
        }
        $arguments = $xml->addChild('arguments');
        foreach ($parser->arguments() as $argument) {
            $argument->xml($arguments);
        }
        $xml->addChild('epilog', $parser->getEpilog());

        return $string ? $xml->asXML() : $xml;
    }
}
