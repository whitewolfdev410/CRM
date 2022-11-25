<?php

namespace App\Core\Logging;

use Monolog\Formatter\LogglyFormatter as BaseFormatter;
use JsonSerializable;
use Traversable;
use Exception;

/**
 * Extends the standard LogglyFormatter.
 *
 * Serializes log context so it can be encoded to json
 */
class LogglyFormatter extends BaseFormatter
{
    /**
     * {@inheritdoc}
     */
    public function format(array $record): string
    {
        if (isset($record['context'])) {
            $record['context'] = $this->serialize($record['context']);
        }

        return parent::format($record);
    }

    /**
     * Serialize data
     *
     * @param  mixed $data
     * @return mixed
     */
    private function serialize($data)
    {
        if ($data instanceof JsonSerializable) {
            return $data;
        }

        if (is_array($data) || $data instanceof Traversable) {
            $serialized = [];

            foreach ($data as $key => $value) {
                $serialized[$key] = $this->serialize($value);
            }

            return $serialized;
        }

        if ($data instanceof Exception) {
            return $this->serializeException($data);
        }

        return $data;
    }

    /**
     * Serialize exception object
     *
     * @param  Exception $e
     * @return array
     */
    private function serializeException(Exception $e)
    {
        $serialized = [
            'class' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile() . ':' . $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];

        if ($previous = $e->getPrevious()) {
            $serialized['previous'] = $this->serializeException($previous);
        }

        return $serialized;
    }
}
