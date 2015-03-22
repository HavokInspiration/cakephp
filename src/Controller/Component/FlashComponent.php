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
 * @since         3.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Controller\Component;

use Cake\Controller\Component;
use Cake\Controller\ComponentRegistry;
use Cake\Network\Exception\InternalErrorException;
use Cake\Utility\Inflector;

/**
 * The CakePHP FlashComponent provides a way for you to write a flash variable
 * to the session from your controllers, to be rendered in a view with the
 * FlashHelper.
 */
class FlashComponent extends Component
{

    /**
     * The Session object instance
     *
     * @var \Cake\Network\Session
     */
    protected $_session;

    /**
     * Default configuration
     *
     * @var array
     */
    protected $_defaultConfig = [
        'key' => 'flash',
        'element' => 'default',
        'params' => [],
        'stacking' => [
            'enabled' => false,
            'limit' => 50
        ]
    ];

    /**
     * Constructor
     *
     * @param ComponentRegistry $registry A ComponentRegistry for this component
     * @param array $config Array of config.
     */
    public function __construct(ComponentRegistry $registry, array $config = [])
    {
        parent::__construct($registry, $config);
        $this->_session = $registry->getController()->request->session();
    }

    /**
     * Used to set a session variable that can be used to output messages in the view.
     *
     * In your controller: $this->Flash->set('This has been saved');
     *
     * ### Options:
     *
     * - `key` The key to set under the session's Flash key
     * - `element` The element used to render the flash message. Default to 'default'.
     * - `params` An array of variables to make available when using an element
     *
     * @param string|\Exception $message Message to be flashed. If an instance
     *   of \Exception the exception message will be used and code will be set
     *   in params.
     * @param array $options An array of options
     * @return null|int If stacking is disabled, will return null. Otherwise, it will return the
     * last inserted index to the stack
     */
    public function set($message, array $options = [])
    {
        $options += $this->config();

        if ($message instanceof \Exception) {
            $options['params'] += ['code' => $message->getCode()];
            $message = $message->getMessage();
        }

        list($plugin, $element) = pluginSplit($options['element']);

        if ($plugin) {
            $options['element'] = $plugin . '.Flash/' . $element;
        } else {
            $options['element'] = 'Flash/' . $element;
        }

        $sessionKey = 'Flash.' . $options['key'];
        if ($this->config('stacking.enabled') === true) {
            $messages = $this->_session->read($sessionKey);

            if ($messages === null) {
                $index = 0;
            } elseif (is_array($messages) && !is_numeric(key($messages))) {
                $this->_session->delete($sessionKey);
                $this->_session->write($sessionKey . '.0', $messages);
                $index = 1;
            } else {
                end($messages);
                $index = key($messages);
                reset($messages);
                $index++;
            }

            if ($index >= $this->config('stacking.limit')) {
                array_shift($messages);
                $this->_session->write($sessionKey, $messages);
                $index--;
            }

            $sessionKey .= '.' . $index;
        }
        $this->_session->write($sessionKey, [
            'message' => $message,
            'key' => $options['key'],
            'element' => $options['element'],
            'params' => $options['params']
        ]);

        return isset($index) ? $index : null;
    }

    /**
     * Delete a message from the session
     * If there are no remaining messages (in case of a stack), the stack
     * array is nulled
     *
     * @param string $key The flash key where the message is stored
     * @param null|string $index The index of the message to delete
     * @return void
     */
    public function delete($key = '', $index = null)
    {
        if (empty($key)) {
            $key = $this->config('key');
        }

        $sessionKey = $noIndexKey = 'Flash.' . $key;
        if ($index !== null) {
            $sessionKey .= '.' . $index;
        }

        if ($this->_session->check($sessionKey)) {
            $this->_session->delete($sessionKey);
        }

        $remaining = $this->_session->read($noIndexKey);
        if (is_array($remaining) && empty($remaining)) {
            $this->_session->delete($noIndexKey);
        }
    }

    /**
     * Delete all messages from a special type
     *
     * @param string $type The type of message to clear
     * @param string $key The flash key where the message is stored
     * @return void
     */
    public function clear($type, $key = '')
    {
        if (empty($key)) {
            $key = $this->config('key');
        }

        $messages = $this->_session->read('Flash.' . $key);
        if (!is_numeric(key($messages)) && $this->_hasType($messages, $type)) {
            $this->delete($key);
        } else {
            foreach ($messages as $index => $message) {
                if ($this->_hasType($message, $type)) {
                    $this->delete($key, $index);
                }
            }
        }
    }

    /**
     * Check if the given $message is of type $type
     *
     * @param array $message Flash message array to test the type of
     * @param string $type Type of message to test against
     * @return bool
     */
    protected function _hasType(array $message, $type)
    {
        $elementParts = explode('/', $message['element']);
        $messageType = end($elementParts);
        return $messageType === $type;
    }

    /**
     * Magic method for verbose flash methods based on element names.
     *
     * For example: $this->Flash->success('My message') would use the
     * success.ctp element under `src/Template/Element/Flash` for rendering the
     * flash message.
     *
     * Note that the parameter `element` will be always overridden. In order to call a
     * specific element from a plugin, you should set the `plugin` option in $args.
     *
     * For example: `$this->Flash->warning('My message', ['plugin' => 'PluginName'])` would
     * use the warning.ctp element under `plugins/PluginName/src/Template/Element/Flash` for
     * rendering the flash message.
     *
     * @param string $name Element name to use.
     * @param array $args Parameters to pass when calling `FlashComponent::set()`.
     * @return null|int If stacking is disabled, will return null. Otherwise, it will return the
     * last inserted index to the stack
     * @throws \Cake\Network\Exception\InternalErrorException If missing the flash message.
     */
    public function __call($name, $args)
    {
        $element = Inflector::underscore($name);

        if (count($args) < 1) {
            throw new InternalErrorException('Flash message missing.');
        }

        $options = ['element' => $element];

        if (!empty($args[1])) {
            if (!empty($args[1]['plugin'])) {
                $options = ['element' => $args[1]['plugin'] . '.' . $element];
                unset($args[1]['plugin']);
            }
            $options += (array)$args[1];
        }

        return $this->set($args[0], $options);
    }
}
