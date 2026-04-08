<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;
use Twilio\Rest\Client as TwilioClient;

class SmsService
{
    private $apiKey;
    private $apiUrl;
    private $apiSecret;
    private $senderId;
    private $verifyServiceId;
    private $logger;
    private $enableDevMode;
    private $smsProvider;
    private $countryCode;
    private $verificationChannel;
    private $whatsappSandboxNumber;
    private $whatsappApiMethod;

    public function __construct(
        ParameterBagInterface $params,
        LoggerInterface $logger,
        string $smsProvider = 'twilio',
        string $smsApiKey = '',
        string $smsApiSecret = '',
        string $countryCode = '+216',
        string $senderPhone = '+21696153831',
        string $verifyServiceId = '',
        string $verificationChannel = 'whatsapp',
        string $whatsappSandboxNumber = 'whatsapp:+14155238886',
        string $whatsappApiMethod = 'verify'
    )
    {
        // Use injected parameters from Symfony config, fall back to environment variables
        $this->smsProvider = $smsProvider ?: ($_ENV['SMS_PROVIDER'] ?? getenv('SMS_PROVIDER') ?? 'demo');
        $this->apiKey = $smsApiKey ?: ($_ENV['SMS_API_KEY'] ?? getenv('SMS_API_KEY') ?? '');
        $this->apiSecret = $smsApiSecret ?: ($_ENV['SMS_API_SECRET'] ?? getenv('SMS_API_SECRET') ?? '');
        $this->countryCode = $countryCode ?: ($_ENV['SMS_COUNTRY_CODE'] ?? getenv('SMS_COUNTRY_CODE') ?? '+216');
        $this->senderId = $senderPhone ?: ($_ENV['SMS_SENDER_PHONE'] ?? getenv('SMS_SENDER_PHONE') ?? '+21696153831');
        
        // Twilio Verify Service ID
        $this->verifyServiceId = $verifyServiceId ?: ($_ENV['TWILIO_VERIFY_SERVICE_ID'] ?? getenv('TWILIO_VERIFY_SERVICE_ID') ?? '');
        
        // Verification channel preference (whatsapp or sms)
        $this->verificationChannel = $verificationChannel ?: ($_ENV['VERIFICATION_CHANNEL'] ?? getenv('VERIFICATION_CHANNEL') ?? 'whatsapp');
        
        // WhatsApp configuration
        $this->whatsappSandboxNumber = $whatsappSandboxNumber ?: ($_ENV['WHATSAPP_SANDBOX_NUMBER'] ?? getenv('WHATSAPP_SANDBOX_NUMBER') ?? 'whatsapp:+14155238886');
        $this->whatsappApiMethod = $whatsappApiMethod ?: ($_ENV['WHATSAPP_API_METHOD'] ?? getenv('WHATSAPP_API_METHOD') ?? 'verify');
        
        $this->logger = $logger;
        $this->apiUrl = $_ENV['SMS_API_URL'] ?? getenv('SMS_API_URL') ?? '';
        
        // Set dev mode based on SMS_PROVIDER
        $this->enableDevMode = ($this->smsProvider === 'demo');
    }

    /**
     * Send SMS with verification code using Twilio Verify Service
     * Supports: whatsapp, email, sms
     * 
     * @param string $phoneNumber Phone number (8 digits for Tunisian: 12345678)
     * @param string $code 6-digit verification code (not used with Verify service - it generates one)
     * @return bool True if verification SMS sent successfully
     */
    public function sendVerificationCode(string $phoneNumber, string $code): bool
    {
        // If using Twilio Verify service, ignore the passed code and let Verify generate one
        if ($this->smsProvider === 'twilio' && !empty($this->verifyServiceId)) {
            $result = $this->sendViaVerify($phoneNumber);
            
            // If Verify Service fails, try WhatsApp Direct API as fallback
            if (!$result && $this->whatsappApiMethod === 'api') {
                $this->logger->info("Twilio Verify Service failed, trying WhatsApp Direct API as fallback");
                return $this->sendViaWhatsAppApi($phoneNumber, $code);
            }
            
            return $result;
        }
        
        // Fall back to regular SMS if Verify service not configured
        $message = "Your Credix verification code is: $code. This code expires in 15 minutes.";
        return $this->sendSms($phoneNumber, $message);
    }

    /**
     * Verify a code sent via Twilio Verify Service
     * 
     * @param string $phoneNumber Phone number (8 digits for Tunisian: 12345678)
     * @param string $code 6-digit code to verify
     * @return bool True if code is valid
     */
    public function verifyCode(string $phoneNumber, string $code): bool
    {
        if ($this->smsProvider === 'twilio' && !empty($this->verifyServiceId)) {
            // First, try to verify via Twilio Verify Service
            $result = $this->verifyViaService($phoneNumber, $code);
            
            // If Twilio verification fails, it might be because we used WhatsApp Direct API fallback
            // In that case, return false and let the controller handle database verification
            return $result;
        }
        
        // If not using Twilio Verify service, cannot verify
        return false;
    }

    /**
     * Generic SMS sending method
     * 
     * @param string $phoneNumber 8-digit phone number (Tunisian format)
     * @param string $message Message content
     * @return bool True if successful
     */
    public function sendSms(string $phoneNumber, string $message): bool
    {
        // Format phone number to international format (+216...)
        $formattedPhone = $this->formatTunisianPhoneNumber($phoneNumber);

        try {
            // Development mode - log SMS instead of sending
            if ($this->enableDevMode) {
                $this->logger->info("DEV MODE - SMS would be sent to $formattedPhone: $message", [
                    'from' => $this->senderId,
                    'to' => $formattedPhone,
                    'body' => $message,
                    'provider' => $this->smsProvider
                ]);
                return true;
            }

            // Route to appropriate SMS provider
            switch ($this->smsProvider) {
                case 'twilio':
                    if (!empty($this->apiKey) && !empty($this->apiSecret)) {
                        return $this->sendViaTwilio($formattedPhone, $message);
                    } else {
                        $this->logger->error("Twilio SMS credentials not configured");
                        return false;
                    }
                case 'http_api':
                    if (!empty($this->apiUrl) && !empty($this->apiKey)) {
                        return $this->sendViaHttpApi($formattedPhone, $message);
                    } else {
                        $this->logger->error("HTTP API SMS credentials not configured");
                        return false;
                    }
                default:
                    // Unknown provider - log and return false
                    $this->logger->error("Unknown SMS provider: " . $this->smsProvider);
                    return false;
            }

        } catch (\Exception $e) {
            $this->logger->error("Failed to send SMS to $formattedPhone: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Format 8-digit Tunisian phone number to international format
     * Tunisian format: 8 digits (e.g., 12345678 or 96153831)
     * International format: +216 12345678 or +21696153831
     */
    private function formatTunisianPhoneNumber(string $phoneNumber): string
    {
        // Remove any non-digit characters
        $phone = preg_replace('/\D/', '', $phoneNumber);

        // If already has country code (starts with 216), add + prefix
        if (strlen($phone) === 11 && strpos($phone, '216') === 0) {
            return '+' . $phone;
        }

        // If 8 digits (Tunisian local format), add +216 prefix
        if (strlen($phone) === 8) {
            return '+216' . $phone;
        }

        // If 10 digits starting with 0, remove the 0 and add +216
        if (strlen($phone) === 10 && strpos($phone, '0') === 0) {
            $phone = substr($phone, 1);
            return '+216' . $phone;
        }

        // If already formatted with +216, return as is
        if (strpos($phone, '216') === 0) {
            return '+' . $phone;
        }

        // Default: assume it's 8 digits and add +216
        return '+216' . str_pad($phone, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Send via Twilio SDK
     * Configure in .env:
     * SMS_API_KEY=your_twilio_account_sid
     * SMS_API_SECRET=your_twilio_auth_token
     * SMS_SENDER_PHONE=+21696153831 (Tunisian sender number)
     */
    private function sendViaTwilio(string $phoneNumber, string $message): bool
    {
        try {
            // Log the attempt
            $this->logger->info("Attempting to send SMS via Twilio", [
                'to' => $phoneNumber,
                'from' => $this->senderId,
                'message_length' => strlen($message),
                'has_api_key' => !empty($this->apiKey),
                'has_api_secret' => !empty($this->apiSecret)
            ]);

            // Validate credentials
            if (empty($this->apiKey) || empty($this->apiSecret)) {
                throw new \Exception("Twilio credentials not configured (API Key or Secret missing)");
            }

            // Create Twilio client with Account SID and Auth Token
            $client = new TwilioClient($this->apiKey, $this->apiSecret);
            
            // Send SMS using Twilio API
            $response = $client->messages->create(
                $phoneNumber,  // To number (e.g., +21696153831)
                [
                    'from' => $this->senderId,  // From our Tunisian number
                    'body' => $message
                ]
            );

            $this->logger->info("SMS sent successfully via Twilio", [
                'to' => $phoneNumber,
                'from' => $this->senderId,
                'sid' => $response->sid ?? 'N/A',
                'status' => $response->status ?? 'unknown'
            ]);

            return true;

        } catch (\Twilio\Exceptions\RestException $e) {
            $this->logger->error("Twilio API Error: " . $e->getMessage(), [
                'to' => $phoneNumber,
                'from' => $this->senderId
            ]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error("Twilio SMS error: " . $e->getMessage(), [
                'to' => $phoneNumber,
                'from' => $this->senderId,
                'account_sid' => substr($this->apiKey, 0, 8) . '...',
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Send via generic HTTP API (for other SMS providers)
     * Each provider may have different API structure
     */
    private function sendViaHttpApi(string $phoneNumber, string $message): bool
    {
        $data = [
            'phone' => $phoneNumber,
            'message' => $message,
            'sender' => $this->senderId,
            'api_key' => $this->apiKey
        ];

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            $this->logger->error("HTTP API SMS error", [
                'http_code' => $httpCode,
                'response' => $response,
                'to' => $phoneNumber
            ]);
            return false;
        }

        return true;
    }

    /**
     * Send verification code via Twilio Verify Service
     * The Verify service generates and sends the code automatically
     * Tries WhatsApp first, falls back to SMS if WhatsApp channel is disabled
     * 
     * @param string $phoneNumber Phone number (8 digits for Tunisian: 12345678)
     * @return bool True if verification sent successfully
     */
    private function sendViaVerify(string $phoneNumber): bool
    {
        try {
            // Format phone number to international format
            $formattedPhone = $this->formatTunisianPhoneNumber($phoneNumber);

            // Create Twilio client
            $client = new TwilioClient($this->apiKey, $this->apiSecret);

            // Try preferred channel first (WhatsApp or SMS)
            $primaryChannel = $this->verificationChannel === 'whatsapp' ? 'whatsapp' : 'sms';
            $fallbackChannel = $this->verificationChannel === 'whatsapp' ? 'sms' : 'whatsapp';

            try {
                $this->logger->info("Sending verification code via Twilio Verify Service", [
                    'to' => $formattedPhone,
                    'channel' => $primaryChannel,
                    'verify_service_id' => substr($this->verifyServiceId, 0, 8) . '...'
                ]);

                // Send verification code
                $verification = $client->verify->v2->services($this->verifyServiceId)
                    ->verifications
                    ->create($formattedPhone, $primaryChannel);

                $this->logger->info("Verification code sent successfully via Twilio Verify", [
                    'to' => $formattedPhone,
                    'channel' => $primaryChannel,
                    'status' => $verification->status ?? 'pending'
                ]);

                return true;

            } catch (\Twilio\Exceptions\RestException $e) {
                // If channel is disabled, try fallback
                if (stripos($e->getMessage(), 'channel disabled') !== false || 
                    stripos($e->getMessage(), 'Delivery channel disabled') !== false) {
                    
                    $this->logger->warning("Primary channel ($primaryChannel) disabled, trying fallback ($fallbackChannel)", [
                        'to' => $formattedPhone
                    ]);

                    // Try fallback channel
                    $verification = $client->verify->v2->services($this->verifyServiceId)
                        ->verifications
                        ->create($formattedPhone, $fallbackChannel);

                    $this->logger->info("Verification code sent via fallback channel", [
                        'to' => $formattedPhone,
                        'channel' => $fallbackChannel,
                        'status' => $verification->status ?? 'pending'
                    ]);

                    return true;
                }
                
                // If it's an unverified phone number error on trial account, don't re-throw
                // Let the caller try WhatsApp Direct API fallback
                if (stripos($e->getMessage(), 'unverified') !== false || 
                    stripos($e->getMessage(), 'trial') !== false ||
                    $e->getStatusCode() === 403) {
                    
                    $this->logger->warning("Verify Service failed - phone number unverified or trial account limitation", [
                        'to' => $formattedPhone,
                        'error' => $e->getMessage(),
                        'code' => $e->getStatusCode()
                    ]);
                    
                    return false;  // Return false to trigger fallback in sendVerificationCode
                }
                
                // Re-throw if not a channel disabled or unverified phone error
                throw $e;
            }

        } catch (\Twilio\Exceptions\RestException $e) {
            $this->logger->error("Twilio Verify API Error: " . $e->getMessage(), [
                'to' => $phoneNumber,
                'channel' => $this->verificationChannel
            ]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error("Twilio Verify Service error: " . $e->getMessage(), [
                'to' => $phoneNumber,
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Verify a code sent via Twilio Verify Service
     * 
     * @param string $phoneNumber Phone number (8 digits for Tunisian: 12345678)
     * @param string $code 6-digit code to verify
     * @return bool True if code is valid, false otherwise
     */
    private function verifyViaService(string $phoneNumber, string $code): bool
    {
        try {
            // Format phone number to international format
            $formattedPhone = $this->formatTunisianPhoneNumber($phoneNumber);

            $this->logger->info("Verifying code via Twilio Verify Service", [
                'to' => $formattedPhone,
                'code_length' => strlen($code)
            ]);

            // Create Twilio client
            $client = new TwilioClient($this->apiKey, $this->apiSecret);

            // Check the verification code
            $verificationCheck = $client->verify->v2->services($this->verifyServiceId)
                ->verificationChecks
                ->create([
                    "to" => $formattedPhone,
                    "code" => $code
                ]);

            $isValid = $verificationCheck->status === 'approved';

            if ($isValid) {
                $this->logger->info("Verification code valid", [
                    'to' => $formattedPhone,
                    'status' => $verificationCheck->status
                ]);
            } else {
                $this->logger->warning("Verification code invalid or expired", [
                    'to' => $formattedPhone,
                    'status' => $verificationCheck->status
                ]);
            }

            return $isValid;

        } catch (\Twilio\Exceptions\RestException $e) {
            $this->logger->error("Twilio Verify check error: " . $e->getMessage(), [
                'to' => $phoneNumber
            ]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error("Verification check error: " . $e->getMessage(), [
                'to' => $phoneNumber,
                'exception_class' => get_class($e)
            ]);
            return false;
        }
    }

    /**
     * Send verification code via WhatsApp Direct API
     * Uses Twilio's WhatsApp messaging API to send custom verification codes
     * 
     * @param string $phoneNumber Phone number (8 digits for Tunisian: 12345678)
     * @param string $code 6-digit verification code
     * @return bool True if WhatsApp message sent successfully
     */
    public function sendViaWhatsAppApi(string $phoneNumber, string $code): bool
    {
        try {
            // Format phone number to international format
            $formattedPhone = $this->formatTunisianPhoneNumber($phoneNumber);

            $this->logger->info("Sending verification code via WhatsApp Direct API", [
                'to' => $formattedPhone,
                'code' => strlen($code) . ' digits'
            ]);

            // Create Twilio client
            $client = new TwilioClient($this->apiKey, $this->apiSecret);

            // Send WhatsApp message with verification code
            $message = $client->messages->create(
                "whatsapp:$formattedPhone",  // To: user's WhatsApp number
                [
                    "from" => $this->whatsappSandboxNumber,  // From: Twilio sandbox
                    "body" => "Your Credix verification code is: $code\n\nThis code expires in 10 minutes.\n\nDo not share this code with anyone."
                ]
            );

            $this->logger->info("WhatsApp verification code sent successfully", [
                'to' => $formattedPhone,
                'sid' => $message->sid,
                'status' => $message->status
            ]);

            return true;

        } catch (\Twilio\Exceptions\RestException $e) {
            $this->logger->error("WhatsApp API Error: " . $e->getMessage(), [
                'to' => $phoneNumber
            ]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error("WhatsApp send error: " . $e->getMessage(), [
                'to' => $phoneNumber,
                'exception_class' => get_class($e)
            ]);
            return false;
        }
    }
}
