<?php

declare(strict_types=1);

namespace Depa\SuluGoogleReviewsBundle\Admin;

use Depa\SuluGoogleReviewsBundle\Entity\GoogleReview;
use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItem;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;

class GoogleReviewsAdmin extends Admin
{
    public const LIST_VIEW = 'depa.google_reviews.list';
    public const EDIT_FORM_VIEW = 'depa.google_reviews.edit_form';
    public const LIST_KEY = 'google_reviews';
    public const FORM_KEY = 'google_review_details';

    public function __construct(
        private readonly ViewBuilderFactoryInterface $viewBuilderFactory,
        private readonly SecurityCheckerInterface $securityChecker,
    ) {
    }

    public function configureNavigationItems(NavigationItemCollection $navigationItemCollection): void
    {
        if ($this->securityChecker->hasPermission(GoogleReview::SECURITY_CONTEXT, PermissionTypes::VIEW)) {
            $item = new NavigationItem('Google Bewertungen');
            $item->setView(self::LIST_VIEW);
            $item->setIcon('su-comment');
            $item->setPosition(40);
            $navigationItemCollection->add($item);
        }
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        $listView = $this->viewBuilderFactory
            ->createListViewBuilder(self::LIST_VIEW, '/google-reviews')
            ->setResourceKey(GoogleReview::RESOURCE_KEY)
            ->setListKey(self::LIST_KEY)
            ->addListAdapters(['table'])
            ->setEditView(self::EDIT_FORM_VIEW)
            ->addToolbarActions([]);

        $editFormView = $this->viewBuilderFactory
            ->createResourceTabViewBuilder(self::EDIT_FORM_VIEW, '/google-reviews/:id')
            ->setResourceKey(GoogleReview::RESOURCE_KEY)
            ->setBackView(self::LIST_VIEW);

        $editDetailsFormView = $this->viewBuilderFactory
            ->createFormViewBuilder(self::EDIT_FORM_VIEW . '.details', '/details')
            ->setResourceKey(GoogleReview::RESOURCE_KEY)
            ->setFormKey(self::FORM_KEY)
            ->setTabTitle('sulu_admin.details')
            ->addToolbarActions([new ToolbarAction('sulu_admin.save')])
            ->setParent(self::EDIT_FORM_VIEW);

        $viewCollection->add($listView);
        $viewCollection->add($editFormView);
        $viewCollection->add($editDetailsFormView);
    }

    public function getSecurityContexts(): array
    {
        return [
            self::SULU_ADMIN_SECURITY_SYSTEM => [
                'Google Reviews' => [
                    GoogleReview::SECURITY_CONTEXT => [
                        PermissionTypes::VIEW,
                        PermissionTypes::EDIT,
                    ],
                ],
            ],
        ];
    }
}
