<?php
use PHPMailer\PHPMailer\PHPMailer;

class Mail
{

    const DATASTORAGENOTIFKEY = 'NotificationRecipients';

    const PR = "\r\n\r\n";

    const BR = "\r\n";

    /**
     * This sends alert to admins when something went wrong at system level, i.e.
     * outside implementation or inside
     * implementation but it went fatally wrong
     *
     * This does not catch its own exceptions since if something fails in mail sending, we want to know about it..
     * and if it doesnt send the mails, only way to know about it is to allow whatever fail what was going on at that moment
     *
     * @throws Exception
     * @param Throwable $e
     */
    public static function sendAlertFromException(Throwable $e): void
    {
        if (config::get('SENDMAILS') !== true) {
            log::error('AlertFromException: '.$e);
            return;
        }

        $mail = Mail::getMailer();

        // Recipients

        foreach (Config::get('SYSTEMADMINEMAILS') as $m) {
            $mail->addAddress($m);
        }

        $mail->Subject = 'ReqDC ' . config::get('ENV') . ' error';

        $server = $_SERVER;
        unset($server['PHP_AUTH_PW']);
        $mail->Body = $e->getMessage() . "\n\n" . $e->getTraceAsString() . "\n\n" . "--------- Session ---------\n" . (isset($_SESSION) ? print_r($_SESSION, true):null) . "\n\n" . "--------- Server ---------\n" . print_r($server, true) . "\n\n" . "--------- Request ---------\n" . print_r($_REQUEST, true) . "\n\n" . "--------- Env ---------\n" . print_r($_ENV, true) . "\n\n";

        $mail->send();
    }

    /**
     * This sends customer tenant based on datastorage defined recipients
     *
     * @param string $message
     *            Message Body
     * @param string $category
     *            Datastorage category value
     */
    public static function sendTenantNotification($message, $category)
    {
        
        if (Config::get('SENDMAILS')!== true) {
            log::warn('Tenant notification '.$category. ' : '. $message);
            return;
        }
        $mail = Mail::getMailer();
        $recipients = DataStorage::get($category, Mail::DATASTORAGENOTIFKEY);
        $recipients = explode(',', $recipients);

        $addedOne = false;
        foreach ($recipients as $r) {
            $r = trim($r);
            if (! empty($r) && stripos($r, '@') !== false) {
                $mail->addAddress($r);
                $addedOne = true;
            }
        }

        foreach (Config::get('SYSTEMADMINEMAILS') as $m) {
            $mail->addCC($m);
            $addedOne = true;
        }

        if ($addedOne === false) {
            $e = new Exception('Did not find recipients when sending tenant notification category: ' . $category . "\r\n message:" . $message);
            log::error($e);
            Mail::sendAlertFromException($e);
            return;
        }

        $mail->Subject = 'ReqDC ' . config::get('ENV') . ' notification: ' . $category;
        $mail->Body = "Dear Recipient," . self::PR . "following has occurred in ReqDC " . config::get('ENV') . " environment:" . self::PR . $message . self::PR . self::BR . "Once the issue has been alleviated, the Execution can be run again from ReqDC App: " . Config::get('UIURL') . '/executions/' . log::getExecutionId() . self::PR . "Debug details:" . self::PR . "Implementation ID: " . log::getImplementationId() . self::BR . "Request ID: " . log::getRequestId() . self::BR . "Execution ID: " . log::getExecutionId() . self::BR . "Schedule ID: " . session::getScheduleId() . self::PR . "This is an automated message by ReqDC platform";

        $mail->send();
    }

    private static function getMailer()
    {
        // Server settings
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->setFrom(Config::get('MAILFROM'), 'ReqDC system alert');
        $mail->Host = config::get('MAILSERVER');
        $mail->SMTPAuth = true;
        $mail->Username = config::get('MAILUSERNAME');
        $mail->Password = config::getSecret('MAILPASSWORD');
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;
        $mail->Timeout = 10;
        return $mail;
    }
}