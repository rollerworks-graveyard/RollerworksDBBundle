<?php

/**
 * This file is part of the RollerworksDBBundle.
 *
 * (c) Rollerscapes
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @link    http://projects.rollerscapes.net/RollerFramework
 * @license http://www.opensource.org/licenses/lgpl-license.php LGPL
 */

namespace Rollerworks\DBBundle\EventListener;

use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * User-error ExceptionListener.
 *
 * An user-error is an exception/error thrown by an DB used-defined function,
 * and is intended as a 'last check', so don't use this to validate basic user-input.
 *
 * An user-error is constructed as.
 * Prefix: "translation-key"|param1:value|param2:value2
 *
 * Translation-key is always run trough trans().
 * With the parameters passed as well, parameters are optional.
 *
 * An parameter is constructed as name:value pairs.
 * * Name follows the PHP variable syntax.
 * * The value must be quoted if it contains an '|'.
 *
 * Escaping of quotes is the same as in SQL using the double quote notation.
 * " becomes ""
 *
 * This listener listens to both \PDOException and \Doctrine\DBAL\Driver\OCI8\OCI8Exception
 * and checks if the error-message begins with configured prefix.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
class UserErrorExceptionListener
{
    /**
     * Translator instance
     *
     * @var \Symfony\Component\Translation\TranslatorInterface
     */
    protected $translator;

    /**
     * Exception must be one of the following.
     *
     * @var array
     */
    protected $checkExceptions = array();

    /**
     * Prefix to use for checking user-exception
     *
     * @var string
     */
    protected $errorPrefix;

    /**
     * Constructing
     *
     * @param \Symfony\Component\Translation\TranslatorInterface $translator
     * @param string                                             $errorPrefix      Prefix to use for checking user-exception
     * @param array                                              $checkExceptions  Exceptions to listen to
     *
     * @api
     */
    public function __construct(TranslatorInterface $translator, $errorPrefix = 'app-exception: ', array $checkExceptions = array('PDOException', 'Doctrine\DBAL\Driver\OCI8\OCI8Exception'))
    {
        $this->errorPrefix     = $errorPrefix;
        $this->translator      = $translator;
        $this->checkExceptions = $checkExceptions;
    }

    /**
     * Register the event handler
     *
     * @param \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event
     *
     * @api
     */
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        if (!in_array(get_class($event->getException()), $this->checkExceptions)) {
            return;
        }

        $exceptionMessesage = $event->getException()->getMessage();
        $prefixLength       = mb_strlen($this->errorPrefix);

        // PDO Exception likes to place SQLState before the message
        // And do some other stuff...

        // HT000 is used for MySQL, but there is no support for 'custom errors' yet

        if ($event->getException() instanceof \PDOException) {
            if (!in_array($event->getException()->getCode(), array('P0001' /*, 'HT000'*/))) {
                return;
            }

            // PostgreSQL
            if ('P0001' === $event->getException()->getCode() && preg_match('#^SQLSTATE\[P0001\]: Raise exception: \d+ ERROR:  (.+)#', $exceptionMessesage, $messageMatch)) {
                $exceptionMessesage = $messageMatch[1];
            }
            // MySQL
            /*if ($event->getException()->getCode() === 'HT000') {
                $sExceptionMsg = $aMessage[1];
            }
            */
            // @codeCoverageIgnoreStart
            else {
                return;
            }
            // @codeCoverageIgnoreEnd
        }

        if (mb_substr($exceptionMessesage, 0, $prefixLength) === $this->errorPrefix) {
            $exceptionMessesage = mb_substr($exceptionMessesage, $prefixLength);
            $messageMatch       = $this->_parseMessage($exceptionMessesage);

            $event->setException(new \Exception($this->translator->trans($messageMatch['message'], $messageMatch['params']), 0, $event->getException()));
        }
    }

    /**
     * Parse the user-error message and return the message and parameters.
     *
     * @param string $inputMessage
     * @return Array with 'message' containing the actual message and 'params' containing the parameters
     */
    protected function _parseMessage($inputMessage)
    {
        $parsedParams = array();

        /*
        * ^\s*
        *
        * # Message/keyword
        * ("(?:[^"]+|"")+"|[^|]+)
        *
        *
        * # Catch all the parameters
        * (
        * (?:
        *
        * # Parameter delimiter
        * \s*\|\s*
        *
        * # Parameter name (optional)
        * (?:[a-z_][a-z0-9_]*):
        *
        * # Value (match is performed multiple times. The regex is grouped and executed multiple times)
        * (?:
        *
        * 	# Match quoted value
        * 	"(?:(?:[^"]+|"")+)"
        * |
        * 	# Match none-quoted value
        * 	(?:[^|]+)
        * )
        *
        * )*
        * )?$
        */
        if (preg_match('#^\s*("(?:[^"]+|"")+"|[^|]+)((?:\s*\|\s*(?:[a-z_][a-z0-9_]*):(?:"(?:(?:[^"]+|"")+)"|(?:[^|]+)))*)?$#i', $inputMessage, $errorMatch)) {
            if (!empty($errorMatch[2]) && false !== mb_strpos($errorMatch[2], '|')) {
                preg_match_all('/(?:\s*\|\s*([a-z_][a-z0-9_]*):("(?:(?:[^"]+|"")+)"|(?:[^|]+))\s*)/i', $errorMatch[2], $matchedParams, PREG_SET_ORDER);

                for ($paramIndex = 0; $paramIndex < count($matchedParams); $paramIndex++) {
                    $matchedParams[$paramIndex][2] = rtrim($matchedParams[$paramIndex][2]);

                    // Check for quotes, trim and normalize them
                    if ('"' === mb_substr($matchedParams[$paramIndex][2], 0, 1)) {
                        $matchedParams[$paramIndex][2] = mb_substr($matchedParams[$paramIndex][2], 1, -1);
                        $matchedParams[$paramIndex][2] = str_replace('""', '"', $matchedParams[$paramIndex][2]);
                    }

                    $parsedParams['%' . $matchedParams[$paramIndex][1] . '%'] = $matchedParams[$paramIndex][2];
                }
            }

            $errorMatch[1] = trim($errorMatch[1]);

            // Check for quotes, trim and normalize them
            if ('"' === mb_substr($errorMatch[1], 0, 1)) {
                $errorMatch[1] = mb_substr($errorMatch[1], 1, -1);
                $errorMatch[1] = str_replace('""', '"', $errorMatch[1]);
            }

            return array('message' => $errorMatch[1], 'params' => $parsedParams);
        }
        else {
            return array('message' => $inputMessage, 'params' => array());
        }
    }
}