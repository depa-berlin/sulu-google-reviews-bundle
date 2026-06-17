// @flow
import React from 'react';
import {Grid, Heading, Number as NumberInput, Toggler} from 'sulu-admin-bundle/components';

type Value = {
    blocked?: boolean,
    sortOrder?: number,
};

type Props = {
    disabled: ?boolean,
    onChange: (value: Value) => void,
    onFinish: () => void,
    value: ?Value,
};

const controlColumn = {display: 'flex', alignItems: 'center', justifyContent: 'flex-end', height: '100%'};

export default class GoogleReviewModeration extends React.Component<Props> {
    get currentValue(): Value {
        return this.props.value || {};
    }

    handleBlockedChange = (checked: boolean) => {
        this.props.onChange({...this.currentValue, blocked: checked});
        this.props.onFinish();
    };

    handleSortOrderChange = (value: ?number) => {
        this.props.onChange({...this.currentValue, sortOrder: null == value ? 0 : value});
    };

    handleSortOrderFinish = () => {
        this.props.onFinish();
    };

    render() {
        const disabled = !!this.props.disabled;
        const value = this.currentValue;
        const blocked = true === value.blocked;
        const sortOrder = 'number' === typeof value.sortOrder ? value.sortOrder : 0;

        return (
            <Grid>
                <Grid.Item colSpan={9}>
                    <Heading
                        description="Gesperrte Bewertungen werden im Frontend nicht angezeigt."
                        label="Bewertung sperren"
                    />
                </Grid.Item>
                <Grid.Item colSpan={3}>
                    <div style={controlColumn}>
                        <Toggler checked={blocked} disabled={disabled} onChange={this.handleBlockedChange} />
                    </div>
                </Grid.Item>

                <Grid.Item colSpan={9}>
                    <Heading
                        description={'Für „Eigene Reihenfolge". 0 = keine Priorität (ans Ende), 1, 2, 3 … = Anzeigereihenfolge.'}
                        label="Reihenfolge"
                    />
                </Grid.Item>
                <Grid.Item colSpan={3}>
                    <div style={controlColumn}>
                        <NumberInput
                            alignment="right"
                            disabled={disabled}
                            min={0}
                            onBlur={this.handleSortOrderFinish}
                            onChange={this.handleSortOrderChange}
                            step={1}
                            value={sortOrder}
                        />
                    </div>
                </Grid.Item>
            </Grid>
        );
    }
}
