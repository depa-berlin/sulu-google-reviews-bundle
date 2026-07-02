// @flow
import React from 'react';
import {Divider, Heading, Icon} from 'sulu-admin-bundle/components';
import {translate} from 'sulu-admin-bundle/utils';

type ReviewValue = {
    authorName?: string,
    date?: string,
    originalLanguage?: ?string,
    rating?: number,
    timestamp?: number,
    translations?: {[locale: string]: string},
};

type Props = {
    value: ?ReviewValue,
};

const LOCALE_COLORS = {
    de: {background: '#E6F1FB', color: '#0C447C'},
    en: {background: '#E1F5EE', color: '#0F6E56'},
    fr: {background: '#FBEAF0', color: '#993556'},
};

const RELATIVE_UNITS = [
    ['year', 31536000],
    ['month', 2592000],
    ['week', 604800],
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
];

function localeStyle(locale: string) {
    return LOCALE_COLORS[locale] || {background: '#F1EFE8', color: '#444441'};
}

// Always-current relative time computed from the timestamp (per locale), instead of
// the stored Google string which goes stale.
function relativeTime(timestamp: ?number, locale: string): string {
    if (!timestamp) {
        return '';
    }

    let formatter;
    try {
        formatter = new Intl.RelativeTimeFormat(locale, {numeric: 'always'});
    } catch (e) {
        formatter = new Intl.RelativeTimeFormat('en', {numeric: 'always'});
    }

    const seconds = Math.max(0, Math.floor(Date.now() / 1000) - timestamp);

    for (const [unit, unitSeconds] of RELATIVE_UNITS) {
        if (seconds >= unitSeconds) {
            return formatter.format(-Math.floor(seconds / unitSeconds), unit);
        }
    }

    return formatter.format(0, 'second');
}

export default class GoogleReviewDisplay extends React.Component<Props> {
    renderStars(rating: number) {
        return [1, 2, 3, 4, 5].map((i) => (
            <Icon
                key={i}
                name="fa-star"
                style={{color: i <= rating ? '#f5a623' : '#d8d8d8', marginRight: 2}}
            />
        ));
    }

    render() {
        const value: ReviewValue = this.props.value || {};
        const authorName = value.authorName || '';
        const rating = value.rating || 0;
        const translations = value.translations || {};
        const initials = authorName ? authorName.charAt(0).toUpperCase() : '?';
        const locales = Object.keys(translations);

        return (
            <div>
                <div style={{display: 'flex', alignItems: 'center', gap: 14, marginBottom: 16}}>
                    {/* Kein externes Google-Profilbild: Initialen-Avatar */}
                    <span style={{width: 48, height: 48, borderRadius: '50%', background: '#E6F1FB', color: '#0C447C', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 600, fontSize: 16, flexShrink: 0}}>
                        {initials}
                    </span>
                    <div style={{flex: 1, minWidth: 0}}>
                        <div style={{fontWeight: 600, fontSize: 16}}>{authorName}</div>
                        <div style={{display: 'flex', alignItems: 'center', gap: 12, marginTop: 4}}>
                            <span aria-label={translate('google_reviews.stars_aria', {rating})} style={{display: 'inline-flex', alignItems: 'center', gap: 6}}>
                                {this.renderStars(rating)}
                                <span style={{fontSize: 13, color: '#777'}}>{rating + '/5'}</span>
                            </span>
                            {value.date ? <span style={{fontSize: 13, color: '#777'}}>{value.date}</span> : null}
                        </div>
                    </div>
                    {value.originalLanguage
                        ? (
                            <span style={{background: '#f4f4f4', color: '#666', fontSize: 12, padding: '4px 10px', borderRadius: 4, whiteSpace: 'nowrap'}}>
                                {translate('google_reviews.original_language', {language: value.originalLanguage.toUpperCase()})}
                            </span>
                        )
                        : null
                    }
                </div>

                <Divider />

                <Heading label={translate('google_reviews.review_text')} />

                <div style={{display: 'flex', flexDirection: 'column', gap: 8, marginTop: 8}}>
                    {locales.length === 0
                        ? <div style={{fontSize: 13, color: '#999'}}>{translate('google_reviews.no_translations')}</div>
                        : locales.map((locale) => {
                            const ls = localeStyle(locale);
                            const relative = relativeTime(value.timestamp, locale);
                            return (
                                <div key={locale} style={{border: '1px solid #f0f0f0', borderRadius: 4, padding: '10px 12px'}}>
                                    <div style={{display: 'flex', alignItems: 'center', gap: 8, marginBottom: 6}}>
                                        <span style={{background: ls.background, color: ls.color, fontSize: 11, fontWeight: 600, padding: '2px 8px', borderRadius: 4}}>
                                            {locale.toUpperCase()}
                                        </span>
                                        {relative ? <span style={{fontSize: 12, color: '#999'}}>{relative}</span> : null}
                                    </div>
                                    <div style={{fontSize: 14, lineHeight: 1.6, color: '#333'}}>{translations[locale]}</div>
                                </div>
                            );
                        })
                    }
                </div>
            </div>
        );
    }
}
