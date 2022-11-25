<?php

namespace App\Core\Extended;

class MessageBag extends \Illuminate\Support\MessageBag
{
    /**
     * The same as in parent class but it removes making changes for array
     * message (we want to return for some "messages" not only message but also
     * message data to allow create link or do any action
     *
     * @param array $messages
     * @param string $format
     * @param string $messageKey
     *
     * @return array
     */
    protected function transform($messages, $format, $messageKey)
    {
        $messages = (array)$messages;

        // We will simply spin through the given messages and transform each one
        // replacing the :message place holder with the real message allowing
        // the messages to be easily formatted to each developer's desires.
        foreach ($messages as &$message) {
            if (!is_array($message)) {
                $replace = [':message', ':key'];

                $message = str_replace(
                    $replace,
                    [$message, $messageKey],
                    $format
                );
            }
        }

        return $messages;
    }
}
