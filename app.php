<?php
require_once('vendor/autoload.php');

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use PAMI\Client\Impl\ClientImpl;
use PAMI\Listener\IEventListener;
use PAMI\Message\Event\EventMessage;

define('MYSQL_HOST', 'mysql');
define('MYSQL_USER', 'root');
define('MYSQL_PASSWORD', '123123');
define('MYSQL_DATABASE', 'dealerspackage');
define('MYSQL_DATABASE_SMS_TABLE', 'sms_table');


define('YEASTAR_HOST',     '82.117.244.82');
define('YEASTAR_PORT',     '55038');
define('YEASTAR_USERNAME', 'apiadmin');
define('YEASTAR_SECRET',   'p89ol11zCx0');


class A implements IEventListener
{
    private $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
        $this->initDb();
    }

    public function handle(EventMessage $event)
    {

        if ($event->getName() === 'ReceivedSMS') {

            $gsmspan  = $event->getKey('gsmspan');
            $sender   = $event->getKey('sender');
            $recvtime = $event->getKey('recvtime');
            $content  = $event->getKey('content');
            $decoded  = urldecode($content);

            //This Handler will print the incoming message.
            $this->output->writeln('');
            $this->output->writeln('===============================================');
            $this->output->writeln("Message Received from: ". $sender);
            $this->output->writeln("Gsm span: ". $gsmspan);
            $this->output->writeln('Message:');
            $this->output->writeln($content);
            $this->output->writeln('Decoded message:');
            $this->output->writeln($decoded);

            $this->persistMessage($gsmspan, $sender, $content, $decoded, $recvtime);

        }
    }

    private function initDb()
    {
        $mysqli = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE);
        if ($mysqli->connect_errno) {
            print("Error: Failed to make a MySQL connection, here is why:");
            print("Errno: " . $mysqli->connect_errno);
            print("Error: " . $mysqli->connect_error);
            exit;
        }

        $sql = "CREATE TABLE IF NOT EXISTS  `%s` (
                `id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
                `gsmspan` VARCHAR( 255 ) NOT NULL ,
                `sender` VARCHAR( 255 ) NOT NULL ,
                `decodedcontent` TEXT NOT NULL ,
                `content` TEXT NOT NULL ,
                `recvtime` DATETIME NOT NULL,
                `savetime` DATETIME NOT NULL
                ) ENGINE = MyIsam";

        if (!$result = $mysqli->query(sprintf($sql, MYSQL_DATABASE_SMS_TABLE))) {
            echo "Sorry, mysql query failed";
            exit;
        }

        $mysqli->close();
    }

    private function persistMessage($gsmspan, $sender, $content, $decodedcontent, $recvtime)
    {
        $mysqli = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE);
        if ($mysqli->connect_errno) {
            print("Error: Failed to make a MySQL connection, here is why:");
            print("Errno: " . $mysqli->connect_errno);
            print("Error: " . $mysqli->connect_error);
            exit;
        }

        /* change character set to utf8 */
        if (!$mysqli->set_charset("utf8")) {
            printf("Error loading character set utf8: %s\n", $mysqli->error);
            exit();
        }

        $sql = sprintf("INSERT INTO `%s` (`id`, `gsmspan`, `sender`, `content`, `decodedcontent`, `recvtime`, `savetime`) VALUES (
            null,
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            NOW()
        )", MYSQL_DATABASE_SMS_TABLE,
            $mysqli->real_escape_string($gsmspan),
            $mysqli->real_escape_string($sender),
            $mysqli->real_escape_string($content),
            $mysqli->real_escape_string($decodedcontent),
            $mysqli->real_escape_string($recvtime)
        );

        if (!$result = $mysqli->query($sql)) {
            echo "Sorry, mysql query failed";
            exit;
        }

        $mysqli->close();

    }
}

$console = new Application();
$console
    ->register('yeastar:listen')
    ->setDefinition(array(

    ))
    ->setDescription('Listen for sms received on Yeastar device and save them to mysql database')
    ->setHelp("")
    ->setCode(function (InputInterface $input, OutputInterface $output) {

        try {

            $options = array(
                'host'            => YEASTAR_HOST,
                'port'            => YEASTAR_PORT,
                'username'        => YEASTAR_USERNAME,
                'secret'          => YEASTAR_SECRET,
                'connect_timeout' => 60,
                'read_timeout'    => 60
            );

            $a = new ClientImpl($options);

            $a->registerEventListener(new A($output));
            $a->open();
            while(true)
            {
                usleep(1000);
                $a->process();
            }

            $a->close();

        } catch (Exception $e) {
            $output->writeln($e->getMessage());
        }

    });

$console->run();
