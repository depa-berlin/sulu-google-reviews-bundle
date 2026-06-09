<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle\Controller\Admin;

use Depa\SuluGoogleReviewsBundle\Entity\GoogleReview;
use Depa\SuluGoogleReviewsBundle\Repository\GoogleReviewRepository;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Sulu\Component\Rest\RestHelperInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class GoogleReviewController extends AbstractController
{
    public function __construct(
        private readonly GoogleReviewRepository $repository,
        private readonly RestHelperInterface $restHelper,
        private readonly DoctrineListBuilderFactoryInterface $listBuilderFactory,
        private readonly FieldDescriptorFactoryInterface $fieldDescriptorFactory,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    public function cgetAction(): JsonResponse
    {
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

    #[IsGranted('ROLE_USER')]
    public function getAction(int $id): JsonResponse
    {
        $review = $this->repository->find($id);

        if (!$review instanceof GoogleReview) {
            throw $this->createNotFoundException(\sprintf('Google Review with id "%d" not found.', $id));
        }

        return $this->json($review->mapToArray());
    }

    #[IsGranted('ROLE_USER')]
    public function putAction(int $id, Request $request): JsonResponse
    {
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
}
