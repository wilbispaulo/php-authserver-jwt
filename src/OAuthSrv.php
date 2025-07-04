<?php

namespace AuthServerJwt;

use DateTime;
use DateTimeZone;
use Jose\Component\Core\JWK;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\HS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;

class OAuthSrv
{
    private JWK $privateJWK;

    public function __construct(
        private string $clientAud,
        private string $secretAlg
    ) {
        $this->setJWKPrivateKey();
    }

    private function setJWKPrivateKey()
    {
        $this->privateJWK = JWKFactory::createFromSecret(
            $this->secretAlg,
            [
                'alg' => 'HS256',
                'use' => 'sig'
            ]
        );
    }

    public function genCredentials(): array
    {
        $credId = microtime(true) . '.' . bin2hex(random_bytes(10));
        $timeCred = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->getTimestamp();
        $clientId = self::uuidv4();
        $credentialPlainText = $this->clientAud . '#' . $clientId . '#' . (string)$timeCred . '%' . $credId;
        $clientSecret = base64_encode(password_hash($credentialPlainText, PASSWORD_BCRYPT));

        return [
            'credential_id' => $credId,
            'credential_time' => $timeCred,
            'client_aud' => $this->clientAud,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ];
    }

    public function tokenJWT(string $issuer, int $tokenExp, array $scope): string
    {
        $baseTime = time();
        $baseClaims = [
            'iat' => $baseTime,
            'nbf' => $baseTime,
            'exp' => $baseTime + $tokenExp,
            'iss' => $issuer,
            'aud' => $this->clientAud,
            'scope' => $scope,
        ];
        $jwsBuilder = new JWSBuilder(new AlgorithmManager([new HS256]));
        $payload = json_encode($baseClaims);

        $jws = $jwsBuilder
            ->create()
            ->withPayload($payload)
            ->addSignature(
                $this->privateJWK,
                [
                    'alg' => 'HS256',
                    'typ' => 'JWT',
                ]
            )
            ->build();
        return (new CompactSerializer)->serialize($jws, 0);
    }

    public static function uuidv4(): string
    {
        $data = random_bytes(16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
