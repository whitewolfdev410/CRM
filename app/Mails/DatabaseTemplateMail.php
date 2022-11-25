<?php

namespace App\Mails;

use App\Modules\EmailTemplate\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DatabaseTemplateMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param EmailTemplate $template
     * @param string        $to
     * @param string|bool   $attachment
     */
    public function __construct(EmailTemplate $template, $to, $attachment = false)
    {
        $this->setData($template, $to, $attachment);
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this;
    }

    /**
     * Set email data
     *
     * @param EmailTemplate $template
     * @param object|array|string  $to
     * @param bool          $attachment
     */
    private function setData(EmailTemplate $template, $to, $attachment = false)
    {
        $this->to($to);
        $this->from($template->getFromEmail(), $template->getFromName());
        $this->subject($template->getSubject());

        if ($template->getIsPlain()) {
            $this->text('emails.database_template');
        } else {
            $this->view('emails.database_template');
        }

        $this->with([
            'body' => $template->getBody()
        ]);

        if (!empty($cc = trim($template->getCcEmail()))) {
            $ccEmails = explode(',', $cc);
            foreach ($ccEmails as $ccEmail) {
                $ccEmail = trim($ccEmail);
                if (!empty($ccEmail)) {
                    $this->cc($ccEmail);
                }
            }
        }

        if (!empty($bcc = trim($template->getBccEmail()))) {
            $bccEmails = explode(',', $cc);
            foreach ($bccEmails as $bccEmail) {
                $bccEmail = trim($bccEmail);
                if (!empty($bccEmail)) {
                    $this->bcc($bccEmail);
                }
            }
        }

        if (!empty($readReceipt = trim($template->getReadReceipt()))) {
            $readReceiptEmails = explode(',', $readReceipt);

            $this->withSwiftMessage(function ($message) use ($readReceiptEmails) {
                $message->setReadReceiptTo($readReceiptEmails);
            });
        }

        if ($attachment) {
            foreach ((array) $attachment as $each) {
                $this->attach($each);
            }
        }
    }
}
