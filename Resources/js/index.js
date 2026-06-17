// @flow
import {fieldRegistry} from 'sulu-admin-bundle/containers';
import GoogleReviewDisplay from './GoogleReviewDisplay';
import GoogleReviewModeration from './GoogleReviewModeration';

fieldRegistry.add('google_review_display', GoogleReviewDisplay);
fieldRegistry.add('google_review_moderation', GoogleReviewModeration);
