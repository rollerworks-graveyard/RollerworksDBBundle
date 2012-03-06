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

namespace Rollerworks\DBBundle\Tests;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\EventListener\ExceptionListener;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Tests\Component\HttpKernel\Logger;

use Rollerworks\DBBundle\EventListener\UserErrorExceptionListener;
use Doctrine\DBAL\Connection;

use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\Translation\Loader\ArrayLoader;

class UserErrorExceptionTest extends \PHPUnit_Framework_TestCase
{
	public function testMessageNoParams()
	{
		$this->_messageTester('Access denied, you don\'t have the correct rights to perform this action.');
		$this->_messageTester('Access denied, you don\'t have the correct rights to perform this action. ', 'Access denied, you don\'t have the correct rights to perform this action.');
		$this->_messageTester('"Access denied, you don\'t have the correct rights to perform this action."', 'Access denied, you don\'t have the correct rights to perform this action.');
		$this->_messageTester('"Access denied, you don\'t have the correct rights to perform this action. "', 'Access denied, you don\'t have the correct rights to perform this action. ');
	}

	public function testMessageKeywordNoParams()
	{
		$this->_messageTester('Access denied, you don\'t have the correct rights to perform this action.');
		$this->_messageTester('access.denied ', 'Access denied, you don\'t have the correct rights to perform this action.');
		$this->_messageTester('"access.denied"', 'Access denied, you don\'t have the correct rights to perform this action.');
	}

	public function testMessageWithParams()
	{
		$this->_messageTester('Access denied, you don\'t have the correct rights to perform this action. user: %user%|user:who', 'Access denied, you don\'t have the correct rights to perform this action. user: who');
		$this->_messageTester('Access denied, you don\'t have the correct rights to perform this action. user: %user% | user:who', 'Access denied, you don\'t have the correct rights to perform this action. user: who');
		$this->_messageTester('Access denied, you don\'t have the correct rights to perform this action. user: %user% | user:who | id:1', 'Access denied, you don\'t have the correct rights to perform this action. user: who');

		$this->_messageTester('Access denied, you don\'t have the correct rights to perform this action. user: %user% with id: %id% | user:who | id:1', 'Access denied, you don\'t have the correct rights to perform this action. user: who with id: 1');
	}

	public function testMessageWithWithParamsAndQuotes()
	{
		$this->_messageTester('"Access denied, you don\'t have the correct rights to perform this action. user: %user%"|user:who', 'Access denied, you don\'t have the correct rights to perform this action. user: who');
		$this->_messageTester('"""Access denied, you don\'t have the correct rights to perform this action. user: %user%"""|user:who', '"Access denied, you don\'t have the correct rights to perform this action. user: who"');
		$this->_messageTester('"Access denied, you don\'t have the correct rights to perform this action. user: ""%user%""|user:who"|user:who', 'Access denied, you don\'t have the correct rights to perform this action. user: "who"|user:who');
		$this->_messageTester('"Access denied, you don\'t have the correct rights to perform this action. user: ""%user%""|user:who"|user:"who"', 'Access denied, you don\'t have the correct rights to perform this action. user: "who"|user:who');
		$this->_messageTester('"Access denied, you don\'t have the correct rights to perform this action. user: ""%user%""|user:""who"""|user:""who""', 'Access denied, you don\'t have the correct rights to perform this action. user: ""who""|user:"who"');
	}

	public function testWrongFormat()
	{
		$this->_messageTester('"Access denied, you don\'t have the correct rights to perform this action. user: %user%"|user:who|', '"Access denied, you don\'t have the correct rights to perform this action. user: %user%"|user:who|');
	}

	public function testWrongInput()
	{
		$oTranslator = new Translator('en', new MessageSelector());

		$this->setExpectedException('\InvalidArgumentException', '$paInExceptions must be an array');

		new UserErrorExceptionListener($oTranslator, 'app-exception', false);
	}

	/**
	 * @param string $psInputMessage
	 * @param string $psExpectedMessage
	 */
	protected function _messageTester($psInputMessage, $psExpectedMessage = null)
	{
		if (empty($psExpectedMessage)) {
			$psExpectedMessage = $psInputMessage;
		}

		/**
		 * Normally an exception gets thrown during an actual request.
		 * But that's to much work for an simple thing like this.
		 *
		 * So instead we create the Kernel, and call handle() which throws an exception.
		 * That exception is then cached and run trough the UserErrorExceptionListener
		 *  which overwrites the exception.
		 *
		 * The 'normal' ExceptionListener works vary differently, and uses the handling of the sup-request for testing.
		 */

		$request = new Request();

		$Kernel2 = new TestKernelThatThrowsException();
		$Kernel3 = new TestKernelThatThrowsPDOException($psInputMessage);
		$Kernel4 = new TestKernelThatThrowsWrongPDOException($psInputMessage);

		$oTranslator = new Translator('en', new MessageSelector());
		$oTranslator->addLoader('array', new ArrayLoader());
		$oTranslator->addResource('array', array((string)trim(trim($psInputMessage), '"') => $psExpectedMessage), 'en');

		$l = new UserErrorExceptionListener($oTranslator);

		try	{
			$Kernel2->handle($request);
		}
		catch (\Exception $e) {
			$l->onKernelException(new GetResponseForExceptionEvent($Kernel2, $request, 'foo', $e));

			$this->assertSame('bar', $e->getMessage());
		}

		try	{
			$Kernel3->handle($request);
		}
		catch (\Exception $e) {
			$oEvent = new GetResponseForExceptionEvent($Kernel3, $request, 'foo', $e);

			$l->onKernelException($oEvent);

			$this->assertSame($psExpectedMessage, $oEvent->getException()->getMessage());
		}

		try	{
			$Kernel4->handle($request);
		}
		catch (\Exception $e) {
			$sExceptionMessage = $e->getMessage();

			$oEvent = new GetResponseForExceptionEvent($Kernel3, $request, 'foo', $e);

			$l->onKernelException($oEvent);

			$this->assertSame($sExceptionMessage, $oEvent->getException()->getMessage());
		}
	}
}


class TestKernel implements HttpKernelInterface
{
	public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true)
	{
		return new Response('foo');
	}
}

class TestKernelThatThrowsException implements HttpKernelInterface
{
	public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true)
	{
		throw new \Exception('bar');
	}
}

class TestKernelThatThrowsPDOException implements HttpKernelInterface
{
	/**
	 * @var \Doctrine\DBAL\Connection
	 */
	protected $_oConn = null;

	/**
	 * @var string
	 */
	protected $_sMessage;

	public function __construct($psMessage)
	{
		$params = array('driver'  	=> 'pdo_pgsql',
						'host'	  	=> 'localhost',
						'db'		=> 'rollerframework',
						'user'	  	=> 'rollerframework',
						'password'  => 'rollerframework');

		$this->_oConn = \Doctrine\DBAL\DriverManager::getConnection($params);

		$this->_sMessage = 'app-exception: ' . $psMessage;
	}

	public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true)
	{
		$this->_oConn->executeQuery("SELECT public.get_message_func(" . $this->_oConn->getWrappedConnection()->quote($this->_sMessage, \PDO::PARAM_STR) . "::text);");
	}
}
class TestKernelThatThrowsWrongPDOException implements HttpKernelInterface
{
	/**
	 * @var \Doctrine\DBAL\Connection
	 */
	protected $_oConn = null;

	/**
	 * @var string
	 */
	protected $_sMessage;

	public function __construct($psMessage)
	{
		$params = array('driver'  	=> 'pdo_pgsql',
						'host'	  	=> 'localhost',
						'db'		=> 'rollerframework',
						'user'	  	=> 'rollerframework',
						'password'  => 'rollerframework');

		$this->_oConn = \Doctrine\DBAL\DriverManager::getConnection($params);

		$this->_sMessage = 'app-exception: ' . $psMessage;
	}

	public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true)
	{
		$this->_oConn->executeQuery("SELECT public.no_func();");
	}
}
