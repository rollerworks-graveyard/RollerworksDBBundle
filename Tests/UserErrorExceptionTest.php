<?php

/**
 * This file is part of the RollerworksDBBundle.
 *
 * (c) Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rollerworks\DBBundle\Tests;

use Rollerworks\Bundle\DBBundle\EventListener\UserErrorExceptionListener;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\EventListener\ExceptionListener;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Doctrine\DBAL\Connection;

class UserErrorExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function testMessageNoParams()
    {
        $this->messageTester('Access denied, you don\'t have the correct rights to perform this action.');
        $this->messageTester('Access denied, you don\'t have the correct rights to perform this action. ', 'Access denied, you don\'t have the correct rights to perform this action.');
        $this->messageTester('"Access denied, you don\'t have the correct rights to perform this action."', 'Access denied, you don\'t have the correct rights to perform this action.');
        $this->messageTester('"Access denied, you don\'t have the correct rights to perform this action. "', 'Access denied, you don\'t have the correct rights to perform this action. ');
    }

    public function testMessageKeywordNoParams()
    {
        $this->messageTester('Access denied, you don\'t have the correct rights to perform this action.');
        $this->messageTester('access.denied ', 'Access denied, you don\'t have the correct rights to perform this action.');
        $this->messageTester('"access.denied"', 'Access denied, you don\'t have the correct rights to perform this action.');
    }

    public function testMessageWithParams()
    {
        $this->messageTester('Access denied, you don\'t have the correct rights to perform this action. user: %user%|user:who', 'Access denied, you don\'t have the correct rights to perform this action. user: who');
        $this->messageTester('Access denied, you don\'t have the correct rights to perform this action. user: %user% | user:who', 'Access denied, you don\'t have the correct rights to perform this action. user: who');
        $this->messageTester('Access denied, you don\'t have the correct rights to perform this action. user: %user% | user:who | id:1', 'Access denied, you don\'t have the correct rights to perform this action. user: who');

        $this->messageTester('Access denied, you don\'t have the correct rights to perform this action. user: %user% with id: %id% | user:who | id:1', 'Access denied, you don\'t have the correct rights to perform this action. user: who with id: 1');
    }

    public function testMessageWithWithParamsAndQuotes()
    {
        $this->messageTester('"Access denied, you don\'t have the correct rights to perform this action. user: %user%"|user:who', 'Access denied, you don\'t have the correct rights to perform this action. user: who');
        $this->messageTester('"""Access denied, you don\'t have the correct rights to perform this action. user: %user%"""|user:who', '"Access denied, you don\'t have the correct rights to perform this action. user: who"');
        $this->messageTester('"Access denied, you don\'t have the correct rights to perform this action. user: ""%user%""|user:who"|user:who', 'Access denied, you don\'t have the correct rights to perform this action. user: "who"|user:who');
        $this->messageTester('"Access denied, you don\'t have the correct rights to perform this action. user: ""%user%""|user:who"|user:"who"', 'Access denied, you don\'t have the correct rights to perform this action. user: "who"|user:who');
        $this->messageTester('"Access denied, you don\'t have the correct rights to perform this action. user: ""%user%""|user:""who"""|user:""who""', 'Access denied, you don\'t have the correct rights to perform this action. user: ""who""|user:"who"');
    }

    public function testWrongFormat()
    {
        $this->messageTester('"Access denied, you don\'t have the correct rights to perform this action. user: %user%"|user:who|', '"Access denied, you don\'t have the correct rights to perform this action. user: %user%"|user:who|');
    }

    /**
     * @param string $inputMessage
     * @param string $expectedMessage
     */
    protected function messageTester($inputMessage, $expectedMessage = null)
    {
        if (empty($expectedMessage)) {
            $expectedMessage = $inputMessage;
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

        $kernel2 = new TestKernelThatThrowsException();
        $kernel3 = new TestKernelThatThrowsPDOException($inputMessage);
        $kernel4 = new TestKernelThatThrowsWrongPDOException($inputMessage);

        $translator = new Translator('en', new MessageSelector());
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', array((string)trim(trim($inputMessage), '"') => $expectedMessage), 'en');

        $l = new UserErrorExceptionListener($translator);

        try	{
            $kernel2->handle($request);
        }
        catch (\Exception $e) {
            $l->onKernelException(new GetResponseForExceptionEvent($kernel2, $request, 'foo', $e));

            $this->assertSame('bar', $e->getMessage());
        }

        try	{
            $kernel3->handle($request);
        }
        catch (\Exception $e) {
            $event = new GetResponseForExceptionEvent($kernel3, $request, 'foo', $e);

            $l->onKernelException($event);

            $this->assertSame($expectedMessage, $event->getException()->getMessage());
        }

        try	{
            $kernel4->handle($request);
        }
        catch (\Exception $e) {
            $sExceptionMessage = $e->getMessage();

            $event = new GetResponseForExceptionEvent($kernel3, $request, 'foo', $e);

            $l->onKernelException($event);

            $this->assertSame($sExceptionMessage, $event->getException()->getMessage());
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
    protected $connection = null;

    /**
     * @var string
     */
    protected $message;

    public function __construct($psMessage)
    {
        $params = array('driver'  	=> 'pdo_pgsql',
                        'host'	  	=> 'localhost',
                        'db'		=> 'rollerframework',
                        'user'	  	=> 'rollerframework',
                        'password'  => 'rollerframework');

        $this->connection = \Doctrine\DBAL\DriverManager::getConnection($params);

        $this->message = 'app-exception: ' . $psMessage;
    }

    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true)
    {
        $this->connection->executeQuery("SELECT public.get_message_func(" . $this->connection->getWrappedConnection()->quote($this->message, \PDO::PARAM_STR) . "::text);");
    }
}

class TestKernelThatThrowsWrongPDOException implements HttpKernelInterface
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection = null;

    /**
     * @var string
     */
    protected $message;

    public function __construct($psMessage)
    {
        $params = array('driver'  	=> 'pdo_pgsql',
                        'host'	  	=> 'localhost',
                        'db'		=> 'rollerframework',
                        'user'	  	=> 'rollerframework',
                        'password'  => 'rollerframework');

        $this->connection = \Doctrine\DBAL\DriverManager::getConnection($params);

        $this->message = 'app-exception: ' . $psMessage;
    }

    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true)
    {
        $this->connection->executeQuery("SELECT public.no_func();");
    }
}
