// @flow
import React from 'react';
import {Divider, Heading, Icon} from 'sulu-admin-bundle/components';

type Translation = {
    relativeTime: string,
    text: string,
};

type ReviewValue = {
    authorName?: string,
    date?: string,
    originalLanguage?: ?string,
    profilePhotoUrl?: ?string,
    rating?: number,
    translations?: {[locale: string]: Translation},
};

type Props = {
    value: ?ReviewValue,
};

const LOCALE_COLORS = {
    de: {background: '#E6F1FB', color: '#0C447C'},
    en: {background: '#E1F5EE', color: '#0F6E56'},
    fr: {background: '#FBEAF0', color: '#993556'},
};

function localeStyle(locale: string) {
    return LOCALE_COLORS[locale] || {background: '#F1EFE8', color: '#444441'};
}

export default class GoogleReviewDisplay extends React.Component<Props> {
    renderStars(rating: number) {
        return [1, 2, 3, 4, 5].map((i) => (
            <Icon
                key={i}
                name="star"
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
                    {value.profilePhotoUrl
                        ? (
                            <img
                                alt={authorName}
                                height={48}
                                src={value.profilePhotoUrl}
                                style={{width: 48, height: 48, borderRadius: '50%', objectFit: 'cover', flexShrink: 0}}
                                width={48}
                            />
                        )
                        : (
                            <span style={{width: 48, height: 48, borderRadius: '50%', background: '#E6F1FB', color: '#0C447C', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 600, fontSize: 16, flexShrink: 0}}>
                                {initials}
                            </span>
                        )
                    }
                    <div style={{flex: 1, minWidth: 0}}>
                        <div style={{fontWeight: 600, fontSize: 16}}>{authorName}</div>
                        <div style={{display: 'flex', alignItems: 'center', gap: 12, marginTop: 4}}>
                            <span>{this.renderStars(rating)}</span>
                            {value.date ? <span style={{fontSize: 13, color: '#777'}}>{value.date}</span> : null}
                        </div>
                    </div>
                    {value.originalLanguage
                        ? (
                            <span style={{background: '#f4f4f4', color: '#666', fontSize: 12, padding: '4px 10px', borderRadius: 4, whiteSpace: 'nowrap'}}>
                                {'Original: ' + value.originalLanguage.toUpperCase()}
                            </span>
                        )
                        : null
                    }
                </div>

                <Divider />

                <Heading label="Bewertungstext" />

                <div style={{display: 'flex', flexDirection: 'column', gap: 8, marginTop: 8}}>
                    {locales.length === 0
                        ? <div style={{fontSize: 13, color: '#999'}}>{'Noch keine Sprachfassungen importiert.'}</div>
                        : locales.map((locale) => {
                            const t = translations[locale];
                            const ls = localeStyle(locale);
                            return (
                                <div key={locale} style={{border: '1px solid #f0f0f0', borderRadius: 4, padding: '10px 12px'}}>
                                    <div style={{display: 'flex', alignItems: 'center', gap: 8, marginBottom: 6}}>
                                        <span style={{background: ls.background, color: ls.color, fontSize: 11, fontWeight: 600, padding: '2px 8px', borderRadius: 4}}>
                                            {locale.toUpperCase()}
                                        </span>
                                        {t.relativeTime ? <span style={{fontSize: 12, color: '#999'}}>{t.relativeTime}</span> : null}
                                    </div>
                                    <div style={{fontSize: 14, lineHeight: 1.6, color: '#333'}}>{t.text}</div>
                                </div>
                            );
                        })
                    }
                </div>
            </div>
        );
    }
}
