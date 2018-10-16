<?php

namespace nikitich\graylog;

use Gelf\Encoder\JsonEncoder;
use Gelf\Message;
use Gelf\Publisher;
use Gelf\Transport\HttpTransport;
use Gelf\Transport\UdpTransport;
use Psr\Log\LogLevel;
use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\VarDumper;
use yii\log\Logger;
use yii\log\Target;

class GraylogTarget extends Target {

    public $server_url  = '127.0.0.1';
    public $server_port = 12201;
    public $use_udp = false;
    public $facility = 'yii2-log';
    public $add_fields = [];

    private $_levels = [
        Logger::LEVEL_TRACE => LogLevel::DEBUG,
        Logger::LEVEL_PROFILE_BEGIN => LogLevel::DEBUG,
        Logger::LEVEL_PROFILE_END => LogLevel::DEBUG,
        Logger::LEVEL_INFO => LogLevel::INFO,
        Logger::LEVEL_WARNING => LogLevel::WARNING,
        Logger::LEVEL_ERROR => LogLevel::ERROR,
    ];

    /**
     * Exports log [[messages]] to a specific destination.
     * Child classes must implement this method.
     */
    public function export()
    {
        if ($this->use_udp === true) {
            $transport = new UdpTransport($this->server_url, $this->server_port, UdpTransport::CHUNK_SIZE_LAN);
        } else {
            $transport = new HttpTransport($this->server_url, $this->server_port);
        }
        $encoder   = new JsonEncoder();
        $publisher = new Publisher();

        $transport->setMessageEncoder($encoder);

        $publisher->addTransport($transport);

        foreach ($this->messages as $message) {

            $gelf_message = $this->getGelfMessageFromLogMessage($message);

            $publisher->publish($gelf_message);
        }
    }

    /**
     * Create instance of Gelf\Message object
     * and set its atributes from log message
     *
     * @param $log_message
     *
     * @return \Gelf\Message
     */
    private function getGelfMessageFromLogMessage($log_message)
    {
        list($body, $level, $category, $timestamp) = $log_message;
        $traces  = (isset($log_message[4]) && is_array($log_message[4])) ? $log_message[4] : [];

        $message = new Message();
        $message->setLevel(ArrayHelper::getValue($this->_levels, $level, LogLevel::INFO))
            ->setFacility($this->facility)
            ->setHost(gethostname())
            ->setTimestamp($timestamp)
            ->setAdditional('_category', $category)
        ;

        if (count($traces) > 0) {
            $message->setAdditional('_traces', $traces)
                ->setFile($traces[0]['file'])
                ->setLine($traces[0]['line'])
            ;

        }

        if (is_string($body)) {
            $message->setShortMessage($body);
        } elseif (is_array($body)) {
            $short = ArrayHelper::remove($text, 'short');
            $full = ArrayHelper::remove($text, 'full');
            $add = ArrayHelper::remove($text, 'add');
            if ($short !== null) {
                $message->setShortMessage($short);
            }

            if ($full !== null) {
                $message->setFullMessage(VarDumper::dumpAsString($full));
            } else {
                $message->setFullMessage(VarDumper::dumpAsString($body));
            }

            if ($add !== null) {
                $add = is_object($add) ? (array) $add : $add;
                $add = is_string($add) ? ['string' => $add] : $add;
                $add = is_numeric($add) ? ['number' => $add] : $add;
                $add = is_array($add) ? $add : [];
                if (is_array($add)) {
                    $message->setAdditional('_data', $add);
                }
            }

        } elseif (is_object($body)) {
            if ($body instanceof \Exception) {
                $message->setShortMessage('Exception ' . get_class($body) . ': ' . $body->getMessage());
                $message->setFullMessage(VarDumper::dumpAsString($body));
                $message->setLine($body->getLine());
                $message->setFile($body->getFile());
            }
        }

        if (is_array($this->add_fields) && count($this->add_fields) > 0) {
            foreach ($this->add_fields as $key => $value) {
                if (is_string($key) && !empty($key)) {
                    if (is_callable($value)) {
                        $value = $value(Yii::$app);
                    }
                    if (!is_string($value) && !empty($value)) {
                        $value = VarDumper::dumpAsString($value);
                    }
                    if (empty($value)) {
                        continue;
                    }

                    $message->setAdditional($key, $value);
                }
            }
        }

        return $message;
    }
}