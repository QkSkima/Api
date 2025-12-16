<?php

declare(strict_types=1);

/*
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace QkSkima\Api\Controller;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use QkSkima\Api\ApiRouter;
use QkSkima\Api\Controller\Attributes\BeforeFilter;
use QkSkima\Api\Controller\Exceptions\ControllerActionNotFound;
use QkSkima\Api\Controller\Exceptions\RequestTokenMissing;
use QkSkima\Api\Controller\Exceptions\RequestTokenNonVerifiable;
use ReflectionClass;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use RuntimeException;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\SecurityAspect;
use TYPO3\CMS\Core\Routing\PageRouter;
use TYPO3\CMS\Core\Security\RequestToken;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;

abstract class AbstractController
{
    protected ServerRequestInterface $request;
    protected RequestHandlerInterface $handler;
    protected ViewFactoryInterface $viewFactory;
    protected ViewInterface $view;
    protected Context $context;
    protected RequestToken $requestToken;

    /** @var ResponseInterface */
    protected ResponseInterface $response;

    protected Site $site;
    protected PageRouter $pageRouter;

    protected array $route;
    protected string $templateRoot;

    const TURBO_STREAM = 'text/vnd.turbo-stream.html';

    public function __construct(
        ServerRequestInterface $request,
        array $route
    ) {
        $this->request = $request;
        $this->route = $route;

        $this->response = new Response();
        $this->viewFactory = GeneralUtility::makeInstance(ViewFactoryInterface::class);

        $this->site = $request->getAttribute('site');
        $this->pageRouter = GeneralUtility::makeInstance(PageRouter::class, $this->site);

        $this->context = GeneralUtility::makeInstance(Context::class);
    }

    public function callAction(string $controller, string $action)
    {
        $this->initializeViewFactory();

        // CSRF check for POST
        if (strtoupper($this->request->getMethod()) === 'POST') {
            $this->validateRequestToken($controller);
        }

        $this->generateRequestToken($controller);
        $this->runBeforeFilters($action);

        if (!method_exists($this, $action)) {
            throw new ControllerActionNotFound("Action $action not found. Have you specified it?");
        }

        $this->resolveContentType();

        return $this->$action();

        // TODO Integrate afterFilters here and automatic call to render method to method names template file
    }

    protected function runBeforeFilters(string $action): void
    {
        $reflection = new ReflectionClass($this);
        $attributes = $reflection->getAttributes(BeforeFilter::class);

        foreach ($attributes as $attr) {
            /** @var BeforeFilter $before */
            $before = $attr->newInstance();

            $shouldRun =
                (empty($before->only) || in_array($action, $before->only, true)) &&
                (empty($before->except) || !in_array($action, $before->except, true));

            if ($shouldRun && method_exists($this, $before->method)) {
                $this->{$before->method}();
            }
        }
    }

    protected function initializeViewFactory(): void
    {
        $templatePaths = [];
        $partialsPaths = [];
        $layoutsPaths  = [];

        if (!is_null($this->getSettings()['api']['templateRootPath']))
            $templatePaths[] = $this->getSettings()['api']['templateRootPath'];
        if (!is_null($this->getSettings()['api']['partialRootPath']))
            $partialsPaths[] = $this->getSettings()['api']['partialRootPath'];
        if (!is_null($this->getSettings()['api']['layoutRootPath']))
            $layoutsPaths[] = $this->getSettings()['api']['layoutRootPath'];

        // Initialize Fluid StandaloneView
        $viewData = new ViewFactoryData(
            templateRootPaths: $templatePaths,
            partialRootPaths:  $partialsPaths,
            layoutRootPaths:   $layoutsPaths,
            request: $this->request
        );
        $this->view = $this->viewFactory->create($viewData);
        $this->view->assign('settings', $this->site->getSettings()->getAll());
    }

    /**
     * Get GET or POST parameter (POST takes precedence)
     */
    protected function getParam(string $name, $default = null)
    {
        $postParams = $this->request->getParsedBody() ?? [];
        $queryParams = $this->request->getQueryParams() ?? [];

        if (isset($postParams[$name])) {
            return $postParams[$name];
        }
        if (isset($queryParams[$name])) {
            return $queryParams[$name];
        }

        return $default;
    }

    public function getSettings(): array
    {
        return $this->site->getConfiguration()['settings'];
    }

    protected function generateRequestToken(string $controllerName): void
    {
        $this->requestToken = RequestToken::create($controllerName);

        $securityAspect = SecurityAspect::provideIn($this->context);
        $signingType = 'nonce';
        $signingProvider = $securityAspect->getSigningSecretResolver()->findByType($signingType);
        if ($signingProvider === null) {
            throw new \LogicException(sprintf('Cannot find request token signing type "%s"', $signingType), 1664260307);
        }

        $signingSecret = $signingProvider->provideSigningSecret();
        $this->requestToken = $this->requestToken->withMergedParams(['request' => ['uri' => $controllerName]]);

        $this->view->assign('requestToken', $this->requestToken->toHashSignedJwt($signingSecret));
    }

    protected function validateRequestToken(string $controllerName): void
    {
        $securityAspect = SecurityAspect::provideIn($this->context);
        $requestToken = $securityAspect->getReceivedRequestToken();

        if ($requestToken === null) {
            throw new RequestTokenMissing('No request token provided');
        } elseif ($requestToken === false) {
            throw new RequestTokenNonVerifiable('Request token was non verifiable. Maybe the nonce cookie was overriden/deleted by another request?');
        } elseif ($requestToken->scope !== $controllerName && !str_starts_with($requestToken->scope, 'core/user-auth/')) {
            throw new RequestTokenMissing('Request token found but was not matching current scope');
        } else {
            // The request token was valid and for the expected scope

            // The middleware takes care to remove the cookie in case no other
            // nonce value shall be emitted during the current HTTP request
            if ($requestToken->getSigningSecretIdentifier() !== null) {
                $securityAspect->getSigningSecretResolver()->revokeIdentifier(
                    $requestToken->getSigningSecretIdentifier(),
                );
            }
        }
    }

    /**
     * Render the Fluid template and write it to the response
     */
    protected function render(string $template = '', string $contentTypeOverride = ''): ResponseInterface
    {
        // Try autoresolve of template
        if (empty($template)) {
            $template = ApiRouter::toCamelCase($this->route['templatePath']);
        }
        
        $this->response->getBody()->write($this->view->render($template));
        return empty($contentTypeOverride) ? $this->response : $this->response->withHeader('Content-Type', $contentTypeOverride);
    }

    protected function resolveContentType(): void
    {
        $this->response = $this->response->withHeader('Content-Type', 'text/html');
    }
}
