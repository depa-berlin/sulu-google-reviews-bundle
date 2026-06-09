<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle\Controller\Admin;

use Depa\SuluGoogleReviewsBundle\Entity\GoogleReview;
use Depa\SuluGoogleReviewsBundle\Repository\GoogleReviewRepository;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class GoogleReviewController extends AbstractController
{
    public function __construct(
        private readonly GoogleReviewRepository $repository,
        private readonly RestHelperInterface $restHelper,
        private readonly DoctrineListBuilderFactoryInterface $listBuilderFactory,
        private readonly FieldDescriptorFactoryInterface $fieldDescriptorFactory,
        private readonly SecurityCheckerInterface $securityChecker,
    ) {
    }

    public function cgetAction(): JsonResponse
    {
        $this->assertPermission(PermissionTypes::VIEW);

        $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors(GoogleReview::RESOURCE_KEY);

        $listBuilder = $this->listBuilderFactory->create($fieldDescriptors['id']->getEntityName());
        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);

        $items = $listBuilder->execute();

        $representation = new PaginatedRepresentation(
            $items,
            GoogleReview::RESOURCE_KEY,
            (int) $listBuilder->getCurrentPage(),
            (int) $listBuilder->getLimit(),
            (int) $listBuilder->count(),
        );

        return $this->json($representation->toArray());
    }

    public function getAction(int $id): JsonResponse
    {
        $this->assertPermission(PermissionTypes::VIEW);

        $review = $this->repository->find($id);

        if (!$review instanceof GoogleReview) {
            throw $this->createNotFoundException(\sprintf('Google Review with id "%d" not found.', $id));
        }

        return $this->json($review->mapToArray());
    }

    public function putAction(int $id, Request $request): JsonResponse
    {
        $this->assertPermission(PermissionTypes::EDIT);

        $review = $this->repository->find($id);

        if (!$review instanceof GoogleReview) {
            throw $this->createNotFoundException(\sprintf('Google Review with id "%d" not found.', $id));
        }

        try {
            $data = $request->toArray();
        } catch (\JsonException) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $review->setBlocked((bool) ($data['blocked'] ?? false));

        $this->repository->save($review);

        return $this->json($review->mapToArray());
    }

    private function assertPermission(string $permissionType): void
    {
        if (!$this->securityChecker->hasPermission(GoogleReview::SECURITY_CONTEXT, $permissionType)) {
            throw new AccessDeniedException();
        }
    }
}
