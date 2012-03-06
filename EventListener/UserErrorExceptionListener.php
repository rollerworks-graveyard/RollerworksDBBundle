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
	protected $_oTranslator;

	/**
	 * Exception must be one of the following.
	 *
	 * @var array
	 */
	protected $_aInExceptions = array();

	/**
	 * Prefix to use for checking user-exception
	 *
	 * @var string
	 */
	protected $_sErrorPrefix;

	/**
	 * Constructing
	 *
	 * @param \Symfony\Component\Translation\TranslatorInterface $poTranslator
	 * @param string                                             $psErrorPrefix   Prefix to use for checking user-exception
	 * @param array                                              $paInExceptions  Exceptions to listen to
	 *
	 * @api
	 */
	public function __construct(TranslatorInterface $poTranslator, $psErrorPrefix = 'app-exception: ', $paInExceptions = array('PDOException', 'Doctrine\DBAL\Driver\OCI8\OCI8Exception'))
	{
		if (! is_array($paInExceptions)) {
			throw (new \InvalidArgumentException('$paInExceptions must be an array'));
		}

		$this->_sErrorPrefix  = $psErrorPrefix;
		$this->_oTranslator   = $poTranslator;
		$this->_aInExceptions = $paInExceptions;
	}

	/**
	 * Register the event handler
	 *
	 * @param \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $poEvent
	 *
	 * @api
	 */
	public function onKernelException(GetResponseForExceptionEvent $poEvent)
	{
		if (! in_array(get_class($poEvent->getException()), $this->_aInExceptions)) {
			return;
		}

		$sExceptionMsg = $poEvent->getException()->getMessage();
		$iPrefixLength = mb_strlen($this->_sErrorPrefix);

		// PDO Exception likes to place SQLState before the message
		// And do some other stuff...

		// HT000 is used for MySQL, but there is no support for 'custom errors' yet

		if ($poEvent->getException() instanceof \PDOException) {
			if (! in_array($poEvent->getException()->getCode(), array('P0001' /*, 'HT000'*/))) {
				return;
			}

			// PostgreSQL
			if ('P0001' === $poEvent->getException()->getCode() && preg_match('#^SQLSTATE\[P0001\]: Raise exception: \d+ ERROR:  (.+)#', $sExceptionMsg, $aMessage)) {
				$sExceptionMsg = $aMessage[ 1 ];
			}
			// MySQL
			/*if ($poEvent->getException()->getCode() === 'HT000') {
				$sExceptionMsg = $aMessage[1];
			}
			*/
			// @codeCoverageIgnoreStart
			else {
				return;
			}
			// @codeCoverageIgnoreEnd
		}

		if ($this->_sErrorPrefix === mb_substr($sExceptionMsg, 0, $iPrefixLength)) {
			$sExceptionMsg = mb_substr($sExceptionMsg, $iPrefixLength);
			$aMessage      = $this->_parseMessage($sExceptionMsg);

			$poEvent->setException(new \Exception($this->_oTranslator->trans($aMessage[ 'message' ], $aMessage[ 'params' ]), 0, $poEvent->getException()));
		}
	}

	/**
	 * Parse the user-error message and return the message and parameters.
	 *
	 * @param string $psMessage
	 * @return Array with 'message' containing the actual message and 'params' containing the parameters
	 */
	protected function _parseMessage($psMessage)
	{
		$aParsedParams = array();

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
		if (preg_match('#^\s*("(?:[^"]+|"")+"|[^|]+)((?:\s*\|\s*(?:[a-z_][a-z0-9_]*):(?:"(?:(?:[^"]+|"")+)"|(?:[^|]+)))*)?$#i', $psMessage, $aErrInformation)) {
			if (!empty($aErrInformation[ 2 ]) && false !== mb_strpos($aErrInformation[ 2 ], '|')) {
				preg_match_all('/(?:\s*\|\s*([a-z_][a-z0-9_]*):("(?:(?:[^"]+|"")+)"|(?:[^|]+))\s*)/i', $aErrInformation[ 2 ], $aParams, PREG_SET_ORDER);

				for ($iParam = 0; $iParam < count($aParams); $iParam ++) {
					$aParams[ $iParam ][ 2 ] = rtrim($aParams[ $iParam ][ 2 ]);

					// Check for quotes, trim and normalize them
					if ('"' === mb_substr($aParams[ $iParam ][ 2 ], 0, 1)) {
						$aParams[ $iParam ][ 2 ] = mb_substr($aParams[ $iParam ][ 2 ], 1, -1);
						$aParams[ $iParam ][ 2 ] = str_replace('""', '"', $aParams[ $iParam ][ 2 ]);
					}

					$aParsedParams[ '%' . $aParams[ $iParam ][ 1 ] . '%' ] = $aParams[ $iParam ][ 2 ];
				}
			}

			$aErrInformation[ 1 ] = trim($aErrInformation[ 1 ]);

			// Check for quotes, trim and normalize them
			if ('"' === mb_substr($aErrInformation[ 1 ], 0, 1)) {
				$aErrInformation[ 1 ] = mb_substr($aErrInformation[ 1 ], 1, -1);
				$aErrInformation[ 1 ] = str_replace('""', '"', $aErrInformation[ 1 ]);
			}

			return array('message' => $aErrInformation[ 1 ], 'params' => $aParsedParams);
		}
		else {
			return array('message' => $psMessage, 'params' => array());
		}
	}
}