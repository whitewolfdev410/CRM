<?php

namespace App\Core\Logging;

use MarvinLabs\DiscordLogger\Converters\RichRecordConverter;
use MarvinLabs\DiscordLogger\Discord\Message;
use MarvinLabs\DiscordLogger\Discord\Embed;
use JsonSerializable;
use Traversable;
use Exception;

class DiscordConverter extends RichRecordConverter
{
    protected function addMainEmbed(Message $message, array $record): void
    {
        parent::addMainEmbed($message, $record);

        $mainEmbed = last($message->embeds);

        // add @here mention
        $mainEmbed->description($mainEmbed->description . ' @here');
    }

    protected function getStacktrace(array $record): ?string
    {
        // prevent default exception formatting
        return null;
    }

    protected function addContextEmbed(Message $message, array $record): void
    {
        if (empty($record['context'])) {
            return;
        }

        // serialize context data
        $context = $this->serialize($record['context']);

        $message->embed(Embed::make()
            ->color($this->getRecordColor($record))
            ->description("**Context**\n`" . json_encode($context, JSON_PRETTY_PRINT) . '`'));
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
            'trace' => '(see Laravel logs for details)',
        ];

        if ($previous = $e->getPrevious()) {
            $serialized['previous'] = $this->serializeException($previous);
        }

        return $serialized;
    }
}
