<?php

/**
 * Use PHPMailer as a base class and extend it
 *
 * @author Gergely Nagy <gna@r-us.hu>
 * @version 2024-12-24
 * @package mailzuMailer
 *
 */
class mailzuMailer extends PHPMailer
{
    /**
     * mailzuMailer constructor.
     *
     * @param bool|null $exceptions
     * 
     */
    public function __construct($exceptions)
    {
        global $conf;
        global $charset;

        //Don't forget to do this or other things may not be set correctly!
        parent::__construct($exceptions);
        $this->CharSet = $charset;

        if ($lang = determine_language()) {    // Functions exist in the langs.php file
            $this->setLanguage($lang);
        }

        switch ($conf['app']['emailType']) {
            case 'smtp':
                $this->isSMTP();
                $this->Mailer = 'smtp';
                $this->Host = $conf['app']['smtpHost'];
                $this->Port = $conf['app']['smtpPort'];
                break;
            case 'sendmail':
                $this->isSendmail();
                $this->Mailer = 'sendmail';
                $this->Sendmail = $conf['app']['sendmailPath'];
                break;
            case 'qmail':
                $this->isQmail();
                $this->Mailer = 'qmail';
                $this->Sendmail = $conf['app']['qmailPath'];
                break;
            case 'mail':
            default:
                $this->isMail();
                $this->Mailer = 'mail';
        }
    }

    //Create the Send() function
    public function Send()
    {
        $this->XMailer = 'mailzu-ng mailer';
        $r = parent::send();
        return $r;
    }

    //Create the AddAddress() function
    public function AddAddress($address, $name = '')
    {
        return parent::addAddress($address, $name);
    }
}
