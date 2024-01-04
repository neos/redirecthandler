<?php
namespace Neos\RedirectHandler;

use GuzzleHttp\Psr7\Uri;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Media\Domain\Model\Dto\AssetResourceReplaced;
use Neos\Media\Domain\Service\AssetResourceReplacementFollowUpInterface;
use Neos\RedirectHandler\Storage\RedirectStorageInterface;

#[Flow\Scope("singleton")]
class AssetRedirectAfterReplacement implements AssetResourceReplacementFollowUpInterface
{
    #[Flow\Inject]
    protected RedirectStorageInterface $redirectStorage;

    #[Flow\Inject]
    protected ResourceManager $resourceManager;

    public function handle(AssetResourceReplaced $assetResourceReplaced): void
    {
        $originalResourceUriString = $this->resourceManager->getPublicPersistentResourceUri($assetResourceReplaced->previousResource);
        $newResourceUriString = $this->resourceManager->getPublicPersistentResourceUri($assetResourceReplaced->newResource);

        if (!is_string($originalResourceUriString) || !is_string($newResourceUriString)) {
            return;
        }

        $existingRedirect = $this->redirectStorage->getOneBySourceUriPathAndHost($originalResourceUriString);
        if ($existingRedirect === null && $originalResourceUriString !== $newResourceUriString) {
            $this->redirectStorage->addRedirect(new Uri($originalResourceUriString), new Uri($newResourceUriString), 301);
        }
    }
}
