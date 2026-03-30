<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class EmailService
{
    public function __construct(private MailerInterface $mailer) {}

    public function sendReservationConfirmation(
        string $to, 
        string $name, 
        string $eventTitle, 
        \DateTimeInterface $eventDate, 
        int $reservationId,
        string $eventLocation
    ): void {
        $email = (new Email())
            ->from('noreply@eventbooking.com')
            ->to($to)
            ->subject('🎫 Reservation Confirmed - EventBooking')
            ->html($this->getEmailTemplate($name, $eventTitle, $eventDate, $reservationId, $eventLocation));

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            // Log l'erreur mais ne bloque pas la réservation
            error_log('Email sending failed: ' . $e->getMessage());
        }
    }

    private function getEmailTemplate(
        string $name, 
        string $eventTitle, 
        \DateTimeInterface $eventDate, 
        int $reservationId,
        string $eventLocation
    ): string {
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Reservation Confirmation</title>
            <style>
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    background-color: #f4f4f4;
                    margin: 0;
                    padding: 0;
                }
                .container {
                    max-width: 600px;
                    margin: 20px auto;
                    background: white;
                    border-radius: 10px;
                    overflow: hidden;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                .header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 30px;
                    text-align: center;
                }
                .header h1 {
                    margin: 0;
                    font-size: 28px;
                }
                .header p {
                    margin: 10px 0 0;
                    opacity: 0.9;
                }
                .content {
                    padding: 30px;
                }
                .event-details {
                    background: #f8f9fa;
                    border-radius: 8px;
                    padding: 20px;
                    margin: 20px 0;
                }
                .event-details h3 {
                    margin-top: 0;
                    color: #667eea;
                }
                .detail-row {
                    display: flex;
                    margin-bottom: 10px;
                }
                .detail-label {
                    font-weight: bold;
                    width: 100px;
                }
                .detail-value {
                    flex: 1;
                }
                .button {
                    display: inline-block;
                    padding: 12px 30px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    text-decoration: none;
                    border-radius: 5px;
                    margin-top: 20px;
                    transition: transform 0.3s;
                }
                .button:hover {
                    transform: translateY(-2px);
                }
                .footer {
                    background: #f8f9fa;
                    padding: 20px;
                    text-align: center;
                    font-size: 12px;
                    color: #666;
                    border-top: 1px solid #eee;
                }
                .reservation-id {
                    background: #667eea;
                    color: white;
                    padding: 5px 10px;
                    border-radius: 5px;
                    display: inline-block;
                    font-size: 12px;
                    margin-top: 10px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🎉 Reservation Confirmed!</h1>
                    <p>Thank you for choosing EventBooking</p>
                </div>
                
                <div class="content">
                    <p>Bonjour <strong>{$name}</strong>,</p>
                    <p>Nous sommes ravis de vous confirmer votre réservation pour l'événement :</p>
                    
                    <div class="event-details">
                        <h3>📅 {$eventTitle}</h3>
                        <div class="detail-row">
                            <div class="detail-label">📆 Date :</div>
                            <div class="detail-value">{$eventDate->format('l d F Y à H:i')}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">📍 Lieu :</div>
                            <div class="detail-value">{$eventLocation}</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">🎟️ ID :</div>
                            <div class="detail-value">#{$reservationId}</div>
                        </div>
                    </div>
                    
                    <p>Vous pouvez consulter le détail de votre réservation à tout moment :</p>
                    <center>
                        <a href="http://localhost:8000/reservation/{$reservationId}/confirmation" class="button">
                            📋 Voir ma réservation
                        </a>
                    </center>
                    
                    <p style="margin-top: 20px; font-size: 14px; color: #666;">
                        <strong>Informations importantes :</strong><br>
                        • Veuillez arriver 15 minutes avant le début de l'événement<br>
                        • Présentez cet email à l'entrée<br>
                        • Pour toute modification, contactez-nous 48h avant
                    </p>
                    
                    <div class="reservation-id">
                        Référence: EVT-{$reservationId}
                    </div>
                </div>
                
                <div class="footer">
                    <p>© 2024 EventBooking - Tous droits réservés</p>
                    <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }
}