<template>
    <div>
        <dl v-if="channel.function.name === 'HVAC_THERMOSTAT'">
            <dd>{{ $t('Subfunction') }}</dd>
            <dt>
                <!-- i18n:['thermostatSubfunction_HEAT', 'thermostatSubfunction_COOL'] -->
                <div class="btn-group btn-group-flex">
                    <a :class="'btn ' + (channel.config.subfunction == type ? 'btn-green' : 'btn-default')"
                        v-for="type in ['HEAT', 'COOL']"
                        :key="type"
                        @click="changeSubfunction(type)">
                        {{ $t(`thermostatSubfunction_${type}`) }}
                    </a>
                </div>
            </dt>
        </dl>
        <transition-expand>
            <div class="alert alert-warning mt-2" v-if="!channel.config.mainThermometerChannelId">
                {{ $t('The thermostat will not work if the main thermometer is not set.') }}
            </div>
        </transition-expand>
        <a class="d-flex accordion-header" @click="displayGroup('related')">
            <span class="flex-grow-1">{{ $t('Thermometers configuration') }}</span>
            <span>
                <fa :icon="group === 'related' ? 'chevron-down' : 'chevron-right'"/>
            </span>
        </a>
        <transition-expand>
            <div v-show="group === 'related'">
                <dl>
                    <dd>{{ $t('Main thermometer') }}</dd>
                    <dt>
                        <channels-id-dropdown :params="`function=THERMOMETER,HUMIDITYANDTEMPERATURE&deviceIds=${channel.iodeviceId}`"
                            v-model="channel.config.mainThermometerChannelId" :hide-none="true"
                            @input="$emit('change')"></channels-id-dropdown>
                    </dt>
                </dl>
                <dl>
                    <dd>{{ $t('Aux thermometer') }}</dd>
                    <dt>
                        <channels-id-dropdown :params="`function=THERMOMETER,HUMIDITYANDTEMPERATURE&deviceIds=${channel.iodeviceId}`"
                            v-model="channel.config.auxThermometerChannelId"
                            @input="auxThermometerChanged()"></channels-id-dropdown>
                    </dt>
                </dl>
                <transition-expand>
                    <div v-if="channel.config.auxThermometerChannelId">
                        <dl>
                            <dd>{{ $t('Aux thermometer type') }}</dd>
                            <dt>
                                <div class="dropdown">
                                    <button class="btn btn-default dropdown-toggle btn-block btn-wrapped" type="button"
                                        data-toggle="dropdown">
                                        {{ $t(`auxThermometerType_${channel.config.auxThermometerType}`) }}
                                        <span class="caret"></span>
                                    </button>
                                    <!-- i18n:['auxThermometerType_NOT_SET', 'auxThermometerType_DISABLED', 'auxThermometerType_FLOOR'] -->
                                    <!-- i18n:['auxThermometerType_WATER', 'auxThermometerType_GENERIC_HEATER', 'auxThermometerType_GENERIC_COOLER'] -->
                                    <ul class="dropdown-menu">
                                        <li v-for="type in ['DISABLED', 'FLOOR', 'WATER', 'GENERIC_HEATER', 'GENERIC_COOLER']" :key="type">
                                            <a @click="channel.config.auxThermometerType = type; $emit('change')"
                                                v-show="type !== channel.config.auxThermometerType">
                                                {{ $t(`auxThermometerType_${type}`) }}
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </dt>
                        </dl>
                        <transition-expand>
                            <div v-if="channel.config.auxThermometerType !== 'DISABLED'">
                                <dl class="wide-label">
                                    <dd>
                                        <span v-if="channel.config.auxThermometerType === 'FLOOR'">
                                            {{ $t('Enable floor temperature control') }}
                                        </span>
                                        <span v-else-if="channel.config.auxThermometerType === 'WATER'">
                                            {{ $t('Enable water temperature control') }}
                                        </span>
                                        <span v-else-if="channel.config.auxThermometerType === 'GENERIC_HEATER'">
                                            {{ $t('Enable generic heater temperature control') }}
                                        </span>
                                        <span v-else-if="channel.config.auxThermometerType === 'GENERIC_COOLER'">
                                            {{ $t('Enable generic cooler temperature control') }}
                                        </span>
                                    </dd>
                                    <dt class="text-center">
                                        <toggler v-model="channel.config.auxMinMaxSetpointEnabled" @input="$emit('change')"/>
                                    </dt>
                                </dl>
                                <transition-expand>
                                    <dl v-if="channel.config.auxMinMaxSetpointEnabled">
                                        <template v-for="temp in auxMinMaxTemperatures">
                                            <dd :key="`dd${temp.name}`">{{ $t(`thermostatTemperature_${temp.name}`) }}</dd>
                                            <dt :key="`dt${temp.name}`">
                                                <span class="input-group">
                                                    <input type="number"
                                                        step="0.1"
                                                        :min="temp.min"
                                                        :max="temp.max"
                                                        class="form-control text-center"
                                                        v-model="channel.config.temperatures[temp.name]"
                                                        @change="temperatureChanged(temp.name)">
                                                    <span class="input-group-addon">&deg;C</span>
                                                </span>
                                            </dt>
                                        </template>
                                    </dl>
                                </transition-expand>
                            </div>
                        </transition-expand>
                    </div>
                </transition-expand>
            </div>
        </transition-expand>
        <a class="d-flex accordion-header" @click="displayGroup('freeze')">
            <span class="flex-grow-1" v-if="freezeHeatProtectionTemperatures.length === 2">
                {{ $t('Anti-freeze and overheat protection') }}
            </span>
            <span class="flex-grow-1" v-else-if="freezeHeatProtectionTemperatures[0].name === 'heatProtection'">
                {{ $t('Overheat protection') }}
            </span>
            <span class="flex-grow-1" v-else>{{ $t('Anti-freeze protection') }}</span>
            <span>
                <fa :icon="group === 'freeze' ? 'chevron-down' : 'chevron-right'"/>
            </span>
        </a>
        <transition-expand>
            <div v-show="group === 'freeze'">
                <dl>
                    <dd>{{ $t('Enabled') }}</dd>
                    <dt class="text-center">
                        <toggler v-model="channel.config.antiFreezeAndOverheatProtectionEnabled" @input="$emit('change')"/>
                    </dt>
                </dl>
                <transition-expand>
                    <dl v-if="channel.config.antiFreezeAndOverheatProtectionEnabled" class="wide-label">
                        <template v-for="temp in freezeHeatProtectionTemperatures">
                            <dd :key="`dd${temp.name}`">{{ $t(`thermostatTemperature_${temp.name}`) }}</dd>
                            <dt :key="`dt${temp.name}`">
                                <span class="input-group d-flex align-items-center justify-content-end">
                                    <input type="number"
                                        step="0.1"
                                        :min="temp.min"
                                        :max="temp.max"
                                        class="form-control text-center"
                                        v-model="channel.config.temperatures[temp.name]"
                                        @change="temperatureChanged(temp.name)">
                                    <span class="input-group-addon">&deg;C</span>
                                </span>
                            </dt>
                        </template>
                    </dl>
                </transition-expand>
            </div>
        </transition-expand>
        <a class="d-flex accordion-header" @click="displayGroup('behavior')">
            <span class="flex-grow-1">{{ $t('Behavior settings') }}</span>
            <span>
                <fa :icon="group === 'behavior' ? 'chevron-down' : 'chevron-right'"/>
            </span>
        </a>
        <transition-expand>
            <div v-show="group === 'behavior'">
                <dl>
                    <dd>{{ $t('External sensor disabling the thermostat') }}</dd>
                    <dt>
                        <channels-id-dropdown :params="`type=SENSORNO&deviceIds=${channel.iodeviceId}`"
                            choose-prompt-i18n="Function disabled"
                            v-model="channel.config.binarySensorChannelId"
                            @input="$emit('change')"></channels-id-dropdown>
                    </dt>
                </dl>
                <div v-if="channel.config.availableAlgorithms.length > 1">
                    <dl>
                        <dd>
                            {{ $t('Algorithm') }}
                            <a @click="algorithmHelpShown = !algorithmHelpShown"><i class="pe-7s-help1"></i></a>
                        </dd>
                        <dt>
                            <div class="dropdown">
                                <button class="btn btn-default dropdown-toggle btn-block btn-wrapped" type="button" data-toggle="dropdown">
                                    {{ $t(`thermostatAlgorithm_${channel.config.usedAlgorithm}`) }}
                                    <span class="caret"></span>
                                </button>
                                <!-- i18n:['thermostatAlgorithm_ON_OFF_SETPOINT_MIDDLE', 'thermostatAlgorithm_ON_OFF_SETPOINT_AT_MOST'] -->
                                <ul class="dropdown-menu">
                                    <li v-for="type in channel.config.availableAlgorithms" :key="type">
                                        <a @click="channel.config.usedAlgorithm = type; $emit('change')"
                                            v-show="type !== channel.config.usedAlgorithm">
                                            {{ $t(`thermostatAlgorithm_${type}`) }}
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </dt>
                    </dl>
                    <transition-expand>
                        <div class="well small text-muted p-2 display-newlines" v-if="algorithmHelpShown">
                            {{ $t('thermostatAlgorithm_help') }}
                        </div>
                    </transition-expand>
                </div>
                <dl class="wide-label">
                    <template v-for="temp in histeresisTemperatures">
                        <dd :key="`dd${temp.name}`">{{ $t(`thermostatTemperature_${temp.name}`) }}</dd>
                        <dt :key="`dt${temp.name}`">
                            <span class="input-group">
                                <input type="number"
                                    step="0.1"
                                    :min="temp.min"
                                    :max="temp.max"
                                    class="form-control text-center"
                                    v-model="channel.config.temperatures[temp.name]"
                                    @change="$emit('change')">
                                <span class="input-group-addon">&deg;C</span>
                            </span>
                        </dt>
                    </template>
                </dl>
                <dl class="wide-label">
                    <dd>
                        <span v-if="heatAvailable && !coolAvailable">{{ $t('Minimum ON time before heating can be turned off') }}</span>
                        <span v-else-if="!heatAvailable && coolAvailable">
                            {{ $t('Minimum ON time before cooling can be turned off') }}
                        </span>
                        <span v-else>{{ $t('Minimum ON time before heating/cooling can be turned off') }}</span>
                    </dd>
                    <dt>
                        <span class="input-group">
                            <input type="number"
                                step="1"
                                min="0"
                                max="600"
                                class="form-control text-center"
                                v-model="channel.config.minOnTimeS"
                                @change="$emit('change')">
                            <span class="input-group-addon">
                                {{ $t('sec.') }}
                            </span>
                        </span>
                    </dt>
                    <dd>
                        <span v-if="heatAvailable && !coolAvailable">{{ $t('Minimum OFF time before heating can be turned on') }}</span>
                        <span v-else-if="!heatAvailable && coolAvailable">
                            {{ $t('Minimum OFF time before cooling can be turned on') }}
                        </span>
                        <span v-else>{{ $t('Minimum OFF time before heating/cooling can be turned on') }}</span>
                    </dd>
                    <dt>
                        <span class="input-group">
                            <input type="number"
                                step="1"
                                min="0"
                                max="600"
                                class="form-control text-center"
                                v-model="channel.config.minOffTimeS"
                                @change="$emit('change')">
                            <span class="input-group-addon">
                                {{ $t('sec.') }}
                            </span>
                        </span>
                    </dt>
                </dl>
                <dl class="wide-label">
                    <dd>
                        {{ $t('Output value on error') }}
                        <a @click="outputValueHelpShown = !outputValueHelpShown"><i class="pe-7s-help1"></i></a>
                    </dd>
                    <dt>
                        <select v-model="channel.config.outputValueOnError" @change="$emit('change')" class="form-control">
                            <option v-for="possibleValue in possibleOutputValueOnErrorValues" :value="possibleValue.value"
                                :key="possibleValue.value">
                                {{ $t(possibleValue.label) }}
                            </option>
                        </select>
                    </dt>
                </dl>
                <transition-expand>
                    <div class="well small text-muted p-2 display-newlines" v-if="outputValueHelpShown">
                        {{ $t('thermostatOutputValue_help') }}
                    </div>
                </transition-expand>
                <dl class="wide-label">
                    <dd>{{ $t('Temperature setpoint change switches to manual mode') }}</dd>
                    <dt class="text-center">
                        <toggler v-model="channel.config.temperatureSetpointChangeSwitchesToManualMode" @input="$emit('change')"/>
                    </dt>
                </dl>
            </div>
        </transition-expand>
    </div>
</template>

<script>
    import ChannelsIdDropdown from "@/devices/channels-id-dropdown";
    import TransitionExpand from "@/common/gui/transition-expand.vue";
    import ChannelFunction from "@/common/enums/channel-function";

    export default {
        components: {TransitionExpand, ChannelsIdDropdown},
        props: ['channel'],
        data() {
            return {
                group: undefined,
                algorithmHelpShown: false,
                outputValueHelpShown: false,
            };
        },
        methods: {
            displayGroup(group) {
                if (this.group === group) {
                    this.group = undefined;
                } else {
                    this.group = group;
                }
            },
            changeSubfunction(subfunction) {
                this.channel.config.subfunction = subfunction;
                if (this.channel.config.outputValueOnError) {
                    this.channel.config.outputValueOnError = 0;
                }
                this.$emit('change');
            },
            temperatureChanged(name) {
                if (this.channel.config.temperatures.auxMaxSetpoint !== '' && this.channel.config.temperatures.auxMinSetpoint !== '') {
                    if (name === 'auxMinSetpoint' && this.channel.config.temperatures.auxMaxSetpoint !== '') {
                        this.channel.config.temperatures.auxMaxSetpoint = Math.max(
                            this.channel.config.temperatures.auxMaxSetpoint,
                            +this.channel.config.temperatures.auxMinSetpoint + this.channel.config.temperatureConstraints.autoOffsetMin
                        );
                    } else if (name === 'auxMaxSetpoint') {
                        this.channel.config.temperatures.auxMinSetpoint = Math.min(
                            this.channel.config.temperatures.auxMinSetpoint,
                            +this.channel.config.temperatures.auxMaxSetpoint - this.channel.config.temperatureConstraints.autoOffsetMin
                        );
                    }
                }
                if (this.channel.config.temperatures.heatProtection !== '' && this.channel.config.temperatures.freezeProtection !== '') {
                    if (name === 'freezeProtection') {
                        this.channel.config.temperatures.heatProtection = Math.max(
                            this.channel.config.temperatures.heatProtection,
                            +this.channel.config.temperatures.freezeProtection + this.channel.config.temperatureConstraints.autoOffsetMin
                        );
                    } else if (name === 'heatProtection') {
                        this.channel.config.temperatures.freezeProtection = Math.min(
                            this.channel.config.temperatures.freezeProtection,
                            +this.channel.config.temperatures.heatProtection - this.channel.config.temperatureConstraints.autoOffsetMin
                        );
                    }
                }
                this.$emit('change');
            },
            auxThermometerChanged() {
                const auxType = this.channel.config.auxThermometerType || 'NOT_SET';
                if (this.channel.config.auxThermometerChannelId && auxType === 'NOT_SET') {
                    this.channel.config.auxThermometerType = 'FLOOR';
                } else if (!this.channel.config.auxThermometerChannelId) {
                    this.channel.config.auxThermometerType = 'NOT_SET';
                }
                this.$emit('change');
            }
        },
        computed: {
            availableTemperatures() {
                // i18n:['thermostatTemperature_freezeProtection','thermostatTemperature_eco','thermostatTemperature_comfort']
                // i18n:['thermostatTemperature_boost','thermostatTemperature_heatProtection','thermostatTemperature_histeresis']
                // i18n:['thermostatTemperature_belowAlarm','thermostatTemperature_aboveAlarm','thermostatTemperature_auxMinSetpoint']
                // i18n:['thermostatTemperature_auxMaxSetpoint']
                return Object.keys(this.channel.config.temperatures || {}).map(name => {
                    const constraintName = {histeresis: 'histeresis', auxMinSetpoint: 'aux', auxMaxSetpoint: 'aux'}[name] || 'room';
                    const min = this.channel.config.temperatureConstraints?.[`${constraintName}Min`];
                    const max = this.channel.config.temperatureConstraints?.[`${constraintName}Max`];
                    return {name, min, max};
                })
            },
            auxMinMaxTemperatures() {
                return [
                    this.availableTemperatures.find(t => t.name === 'auxMinSetpoint'),
                    this.availableTemperatures.find(t => t.name === 'auxMaxSetpoint'),
                ].filter(a => a);
            },
            freezeHeatProtectionTemperatures() {
                const temps = [];
                if (this.heatAvailable) {
                    temps.push(this.availableTemperatures.find(t => t.name === 'freezeProtection'));
                }
                if (this.coolAvailable) {
                    temps.push(this.availableTemperatures.find(t => t.name === 'heatProtection'));
                }
                return temps.filter(a => a);
            },
            histeresisTemperatures() {
                return [
                    this.availableTemperatures.find(t => t.name === 'histeresis'),
                ].filter(a => a);
            },
            possibleOutputValueOnErrorValues() {
                const values = [{value: 0, label: 'off'}]; // i18n
                if (this.coolAvailable) {
                    values.push({value: -100, label: 'cool'}); // i18n
                }
                if (this.heatAvailable) {
                    values.push({value: 100, label: 'heat'}); // i18n
                }
                return values;
            },
            heatAvailable() {
                const heatFunctions = [
                    ChannelFunction.HVAC_THERMOSTAT_AUTO,
                    ChannelFunction.HVAC_DOMESTIC_HOT_WATER,
                    ChannelFunction.HVAC_THERMOSTAT_DIFFERENTIAL,
                ];
                return heatFunctions.includes(this.channel.functionId) || this.channel.config?.subfunction === 'HEAT';
            },
            coolAvailable() {
                return [ChannelFunction.HVAC_THERMOSTAT_AUTO].includes(this.channel.functionId)
                    || this.channel.config?.subfunction === 'COOL';
            },
        },
        watch: {
            'channel.config.mainThermometerChannelId'() {
                if (this.channel.config.auxThermometerChannelId === this.channel.config.mainThermometerChannelId) {
                    this.channel.config.auxThermometerChannelId = null;
                }
            },
            'channel.config.auxThermometerChannelId'() {
                if (this.channel.config.auxThermometerChannelId === this.channel.config.mainThermometerChannelId) {
                    this.channel.config.mainThermometerChannelId = null;
                }
            },
        }
    };
</script>

<style lang="scss">
    .accordion-header {
        color: inherit;
        font-size: 1.1em;
        margin: .5em 0;
    }
</style>
