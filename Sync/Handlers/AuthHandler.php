<?php

declare(strict_types=1);

namespace Sync\Handlers;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Models\AccountModel;
use League\OAuth2\Client\Token\AccessTokenInterface;

/**
 * Class ApiService
 * Методы для работы с Api интеграций 
 */
class ApiService 
{
    /** @var string Базовый домен авторизации. */
    private const TARGET_DOMAIN = 'kommo.com';

    /** @var string Файл хранения токенов. */
    private const TOKENS_FILE = './tokens.json';

    /** @var AmoCRMApiClient AmoCRM клиент. */
    private AmoCRMApiClient $apiClient;

    /**
     * ApiService constructor.
     */
    public function __construct()
    {
        $this->apiClient = new AmoCRMApiClient(
            'bbc22ea5-2b96-469d-a294-6e6df7140f45',
            'QKXXMUD9zbe2X0VHnase6CrdjLXh6lsqwcXqOrmvPxRZOMBA1RaUGXR9O7h0gwwv',
            'https://webhook.site/67ebdb09-efaa-471a-ab3b-395b6bd744ac'
        );
    }
    
    /**
     * Получение токена досутпа для аккаунта.
     *
     * @param array $queryParams Входные GET параметры.
     * @return string Имя авторизованного аккаунта.
     */
    public function auth(array $queryParams): string
    {
        /** Занесение системного идентификатора в сессию для реализации OAuth2.0. */
        if (!empty($queryParams['id'])) {
            $_SESSION['service_id'] = $queryParams['id'];
        }

        if (isset($queryParams['referer'])) {
            $this
                ->apiClient
                ->setAccountBaseDomain($queryParams['referer'])
                ->getOAuthClient()
                ->setBaseDomain($queryParams['referer']);
        }

        try {
            if (!isset($queryParams['code'])) {
                $state = bin2hex(random_bytes(16));
                $_SESSION['oauth2state'] = $state;
                if (isset($queryParams['button'])) {
                    echo $this
                        ->apiClient
                        ->getOAuthClient()
                        ->setBaseDomain(self::TARGET_DOMAIN)
                        ->getOAuthButton([
                            'title' => 'Установить интеграцию',
                            'compact' => true,
                            'class_name' => 'className',
                            'color' => 'default',
                            'error_callback' => 'handleOauthError',
                            'state' => $state,
                        ]);
                } else {
                    $authorizationUrl = $this
                        ->apiClient
                        ->getOAuthClient()
                        ->setBaseDomain(self::TARGET_DOMAIN)
                        ->getAuthorizeUrl([
                            'state' => $state,
                            'mode' => 'post_message',
                        ]);
                    header('Location: ' . $authorizationUrl);
                }
                die;
            } elseif (
                empty($queryParams['state']) ||
                empty($_SESSION['oauth2state']) ||
                ($queryParams['state'] !== $_SESSION['oauth2state'])
            ) {
                unset($_SESSION['oauth2state']);
                exit('Invalid state');
            }
        } catch (Throwable $e) {
            die($e->getMessage());
        }

        try {
            $accessToken = $this
                ->apiClient
                ->getOAuthClient()
                ->setBaseDomain($queryParams['referer'])
                ->getAccessTokenByCode($queryParams['code']);

            if (!$accessToken->hasExpired()) {
                $this->saveToken($_SESSION['service_id'], [
                    'base_domain' => $this->apiClient->getAccountBaseDomain(),
                    'access_token' => $accessToken->getToken(),
                    'refresh_token' => $accessToken->getRefreshToken(),
                    'expires' => $accessToken->getExpires(),
                ]);
            }
        } catch (Throwable $e) {
            die($e->getMessage());
        }

        session_abort();
        return $this
            ->apiClient
            ->getOAuthClient()
            ->getResourceOwner($accessToken)
            ->getName();
    }

    private function saveToken(int $serviceId, array $token): void
    {
        $tokens = file_exists(self::TOKENS_FILE)
            ? json_decode(file_get_contents(self::TOKENS_FILE), true)
            : [];
        $tokens[$serviceId] = $token;
        file_put_contents(self::TOKENS_FILE, json_encode($tokens, JSON_PRETTY_PRINT));
    }

    /**
     * Получение токена из файла.
     *
     * @param int $serviceId Системный идентификатор аккаунта.
     * @return AccessToken
     */
    public function readToken(int $serviceId): AccessToken
    {
        try {
            if (!file_exists(self::TOKENS_FILE)) {
                throw new Exception('Tokens file not found.');
            }

            $accesses = json_decode(file_get_contents(self::TOKENS_FILE), true);
            if (empty($accesses[$serviceId])) {
                throw new Exception("Unknown account name \"$serviceId\".");
            }

            return new AccessToken($accesses[$serviceId]);
        } catch (Throwable $e) {
            exit($e->getMessage());
        }
    }


}

/**
 * Class AuthHandler 
 * Класс обработчик
 */
class AuthHandler implements RequestHandlerInterface
{
    // bbc22ea5-2b96-469d-a294-6e6df7140f45 integration id 
    // QKXXMUD9zbe2X0VHnase6CrdjLXh6lsqwcXqOrmvPxRZOMBA1RaUGXR9O7h0gwwv secret key
    // 31435919 user id

    /**
     * Функция обработки информации из запроса
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $auth = new ApiService();
        $auth->auth(['bbc22ea5-2b96-469d-a294-6e6df7140f45']);
        $token = new ApiService();
        $token->saveToken(['31435919','123']);
        return new JsonResponse('Матвей');
    }
}

