<template>
    <div class="details-tabs">
        <div class="container">
            <div class="container" v-if="availableTabs.length > 1">
                <div class="form-group">
                    <ul class="nav nav-tabs">
                        <router-link :to="{name: tabDefinition.route, params: {id: device.id}}" tag="li"
                            v-for="tabDefinition in availableTabs" :key="tabDefinition.id">
                            <a>
                                {{ $t(tabDefinition.header) }}
                                <span v-if="tabDefinition.count !== undefined">({{ tabDefinition.count() }})</span>
                            </a>
                        </router-link>
                    </ul>
                </div>
            </div>
        </div>
        <RouterView :device="device"/>
    </div>
</template>

<script>
    export default {
        props: {
            device: Object,
        },
        data() {
            return {
                tabVisible: true,
                availableTabs: [],
                channelUpdatedListener: undefined,
            };
        },
        methods: {
            rerender() {
                this.tabVisible = false;
                this.$nextTick(() => this.tabVisible = true);
            },
            detectAvailableTabs() {
                this.availableTabs = [];
                this.availableTabs.push({
                    route: 'device.channels',
                    header: 'Channels', // i18n
                });
                if (this.device.relationsCount.managedNotifications) {
                    this.availableTabs.push({
                        route: 'device.notifications',
                        header: 'Notifications', // i18n
                    });
                }
                if (Object.keys(this.device.config || {}).length) {
                    this.availableTabs.push({
                        route: 'device.settings',
                        header: 'Settings', // i18n
                    });
                }
            },
        },
        mounted() {
            this.detectAvailableTabs();
        },
    };
</script>
