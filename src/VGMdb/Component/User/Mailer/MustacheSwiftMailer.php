<?php

namespace VGMdb\Component\User\Mailer;

use VGMdb\Component\User\Model\UserInterface;
use VGMdb\Component\User\Mailer\MailerInterface;
use Symfony\Component\Routing\RouterInterface;
use Psr\Log\LoggerInterface;

/**
 * Mailer with Mustache templating.
 *
 * @author Gigablah <gigablah@vgmdb.net>
 */
class MustacheSwiftMailer implements MailerInterface
{
    protected $mailer;
    protected $router;
    protected $mustache;
    protected $logger;
    protected $parameters;

    public function __construct(\Swift_Mailer $mailer, RouterInterface $router, \Mustache_Engine $mustache, LoggerInterface $logger = null, array $parameters)
    {
        $this->mailer = $mailer;
        $this->router = $router;
        $this->mustache = $mustache;
        $this->logger = $logger;
        $this->parameters = $parameters;
    }

    /**
     * {@inheritDoc}
     */
    public function sendConfirmationEmail(UserInterface $user)
    {
        $template = $this->parameters['user.mailer.confirmation.template'];
        $url = $this->router->generate('user_registration_confirm', array('token' => $user->getConfirmationToken()), true);
        $context = array(
            'user' => $user,
            'confirmationUrl' => $url
        );

        if (null !== $this->logger) {
            $this->logger->info(sprintf('Sending confirmation email to "%s" with url %s', $user->getEmail(), $url));
        }

        $this->sendMessage($template, $context, $this->parameters['user.mailer.confirmation.from_email'], $user->getEmail());
    }

    /**
     * {@inheritDoc}
     */
    public function sendResetPasswordEmail(UserInterface $user)
    {
        $template = $this->parameters['user.mailer.resetpassword.template'];
        $url = $this->router->generate('login_reset', array('token' => $user->getConfirmationToken()), true);
        $context = array(
            'user' => $user,
            'resetUrl' => $url
        );

        if (null !== $this->logger) {
            $this->logger->info(sprintf('Sending password reset email to "%s" with url %s', $user->getEmail(), $url));
        }

        $this->sendMessage($template, $context, $this->parameters['user.mailer.resetpassword.from_email'], $user->getEmail());
    }

    /**
     * {@inheritDoc}
     */
    public function sendNewPasswordEmail(UserInterface $user)
    {
        $template = $this->parameters['user.mailer.newpassword.template'];
        $context = array(
            'user' => $user
        );

        if (null !== $this->logger) {
            $this->logger->info(sprintf('Sending new password notification email to "%s"', $user->getEmail()));
        }

        $this->sendMessage($template, $context, $this->parameters['user.mailer.newpassword.from_email'], $user->getEmail());
    }

    /**
     * Sends a message using SwiftMailer.
     *
     * @param string $templateName
     * @param array  $context
     * @param string $fromEmail
     * @param string $toEmail
     */
    protected function sendMessage($templates, $context, $fromEmail, $toEmail)
    {
        $subject = trim($this->mustache->loadTemplate($templates)->render(array_merge($context, array('subject' => true))));
        $textBody = trim($this->mustache->loadTemplate($templates)->render(array_merge($context, array('text' => true))));
        $htmlBody = trim($this->mustache->loadTemplate($templates)->render(array_merge($context, array('html' => true))));

        $message = \Swift_Message::newInstance()
            ->setSubject($subject)
            ->setFrom($fromEmail)
            ->setTo($toEmail);

        if (!empty($htmlBody)) {
            $message->setBody($htmlBody, 'text/html')->addPart($textBody, 'text/plain');
        } else {
            $message->setBody($textBody);
        }

        $this->mailer->send($message);
    }
}
