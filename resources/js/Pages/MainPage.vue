<script setup>
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import TracerouteVisualization from '@/Components/TracerouteVisualization.vue'

// Props definition
const props = defineProps({
    title: {
        type: String,
        default: 'ThreeScan'
    },
    traceroutes: {
        type: Array,
        default: () => []
    }
})

// Reactive state
const traceroutes = ref(props.traceroutes || [])
const labelOpacity = ref(0.7) // Default opacity for labels
const repulsionForce = ref(0.5) // Default repulsion force
const attractionForce = ref(0.1) // Default attraction force
const isLoading = ref(false)
const error = ref(null)
const host = ref('google.com')
const routeName = ref('')
const maxHops = ref(30)
const timeout = ref(5)

// Load sample data for development/demo purposes
const loadSampleData = () => {
    traceroutes.value = [
        {
            id: 1,
            name: 'Sample Route 1',
            hops: [
                {hop: 1, hostname: 'router1.local', ip: '192.168.1.1', pingTime: 5, ttl: 1},
                {hop: 2, hostname: 'router2.local', ip: '192.168.1.254', pingTime: 15, ttl: 2},
                {hop: 3, hostname: 'isp-gateway.net', ip: '203.0.113.1', pingTime: 25, ttl: 3},
                {hop: 4, hostname: 'backbone1.isp.net', ip: '203.0.113.10', pingTime: 40, ttl: 4},
                {hop: 5, hostname: 'destination.com', ip: '198.51.100.1', pingTime: 60, ttl: 5},
            ]
        },
        {
            id: 2,
            name: 'Sample Route 2',
            hops: [
                {hop: 1, hostname: 'router1.local', ip: '192.168.1.1', pingTime: 5, ttl: 1},
                {hop: 2, hostname: 'router2.local', ip: '192.168.1.254', pingTime: 18, ttl: 2},
                {hop: 3, hostname: 'alt-isp-gateway.net', ip: '203.0.113.2', pingTime: 30, ttl: 3},
                {hop: 4, hostname: 'backbone2.isp.net', ip: '203.0.113.20', pingTime: 55, ttl: 4},
                {hop: 5, hostname: 'backbone3.isp.net', ip: '203.0.113.30', pingTime: 80, ttl: 5},
                {hop: 6, hostname: 'destination.com', ip: '198.51.100.1', pingTime: 100, ttl: 6},
            ]
        },
        {
            id: 3,
            name: 'Sample Route 3',
            hops: [
                {hop: 1, hostname: 'gateway.local', ip: '192.168.2.1', pingTime: 3, ttl: 1},
                {hop: 2, hostname: 'isp-edge1.net', ip: '203.0.113.50', pingTime: 15, ttl: 2},
                {hop: 3, hostname: 'core1.isp.net', ip: '203.0.113.51', pingTime: 25, ttl: 3},
                {hop: 4, hostname: 'core2.isp.net', ip: '203.0.113.52', pingTime: 35, ttl: 4},
                {hop: 5, hostname: 'exchange1.net', ip: '203.0.113.53', pingTime: 45, ttl: 5},
                {hop: 6, hostname: 'edge1.target.com', ip: '198.51.100.10', pingTime: 55, ttl: 6},
                {hop: 7, hostname: 'server.target.com', ip: '198.51.100.11', pingTime: 65, ttl: 7}
            ]
        },
        {
            id: 4,
            name: 'Sample Route 4',
            hops: [
                {hop: 1, hostname: 'home-router.local', ip: '192.168.3.1', pingTime: 2, ttl: 1},
                {hop: 2, hostname: 'isp-pop1.net', ip: '203.0.113.100', pingTime: 12, ttl: 2},
                {hop: 3, hostname: 'isp-core1.net', ip: '203.0.113.101', pingTime: 22, ttl: 3},
                {hop: 4, hostname: 'isp-core2.net', ip: '203.0.113.102', pingTime: 32, ttl: 4},
                {hop: 5, hostname: 'isp-core3.net', ip: '203.0.113.103', pingTime: 42, ttl: 5},
                {hop: 6, hostname: 'cdn-edge1.net', ip: '203.0.113.104', pingTime: 52, ttl: 6},
                {hop: 7, hostname: 'cdn-server1.net', ip: '198.51.100.20', pingTime: 62, ttl: 7},
                {hop: 8, hostname: 'destination-server.com', ip: '198.51.100.21', pingTime: 72, ttl: 8}
            ]
        },
        {
            id: 5,
            name: 'Sample Route 5',
            hops: [
                {hop: 1, hostname: 'local-gateway.net', ip: '192.168.4.1', pingTime: 4, ttl: 1},
                {hop: 2, hostname: 'isp-router1.net', ip: '203.0.113.150', pingTime: 14, ttl: 2},
                {hop: 3, hostname: 'isp-router2.net', ip: '203.0.113.151', pingTime: 24, ttl: 3},
                {hop: 4, hostname: 'isp-router3.net', ip: '203.0.113.152', pingTime: 34, ttl: 4},
                {hop: 5, hostname: 'exchange-point1.net', ip: '203.0.113.153', pingTime: 44, ttl: 5},
                {hop: 6, hostname: 'destination-edge.com', ip: '198.51.100.30', pingTime: 54, ttl: 6},
                {hop: 7, hostname: 'destination-core.com', ip: '198.51.100.31', pingTime: 64, ttl: 7},
                {hop: 8, hostname: 'destination-server.com', ip: '198.51.100.32', pingTime: 74, ttl: 8},
                {hop: 9, hostname: 'final-destination.com', ip: '198.51.100.33', pingTime: 84, ttl: 9}
            ]
        },
        {
            id: 6,
            name: 'Sample Route 6',
            hops: [
                {hop: 1, hostname: 'router-home.local', ip: '192.168.5.1', pingTime: 3, ttl: 1},
                {hop: 2, hostname: 'isp-access1.net', ip: '203.0.113.200', pingTime: 13, ttl: 2},
                {hop: 3, hostname: 'isp-dist1.net', ip: '203.0.113.201', pingTime: 23, ttl: 3},
                {hop: 4, hostname: 'isp-core1.net', ip: '203.0.113.202', pingTime: 33, ttl: 4},
                {hop: 5, hostname: 'isp-peer1.net', ip: '203.0.113.203', pingTime: 43, ttl: 5},
                {hop: 6, hostname: 'dest-edge1.com', ip: '198.51.100.40', pingTime: 53, ttl: 6}
            ]
        },
        {
            id: 7,
            name: 'Sample Route 7',
            hops: [
                {hop: 1, hostname: 'home-gateway.local', ip: '192.168.6.1', pingTime: 2, ttl: 1},
                {hop: 2, hostname: 'isp-node1.net', ip: '203.0.113.250', pingTime: 12, ttl: 2},
                {hop: 3, hostname: 'isp-node2.net', ip: '203.0.113.251', pingTime: 22, ttl: 3},
                {hop: 4, hostname: 'isp-node3.net', ip: '203.0.113.252', pingTime: 32, ttl: 4},
                {hop: 5, hostname: 'isp-node4.net', ip: '203.0.113.253', pingTime: 42, ttl: 5},
                {hop: 6, hostname: 'isp-node5.net', ip: '203.0.113.254', pingTime: 52, ttl: 6},
                {hop: 7, hostname: 'destination.com', ip: '198.51.100.50', pingTime: 62, ttl: 7}
            ]
        },
        {
            id: 8,
            name: 'Sample Route 8',
            hops: [
                {hop: 1, hostname: 'local-net.local', ip: '192.168.7.1', pingTime: 4, ttl: 1},
                {hop: 2, hostname: 'isp-gateway1.net', ip: '203.0.114.10', pingTime: 14, ttl: 2},
                {hop: 3, hostname: 'isp-core1.net', ip: '203.0.114.11', pingTime: 24, ttl: 3},
                {hop: 4, hostname: 'isp-core2.net', ip: '203.0.114.12', pingTime: 34, ttl: 4},
                {hop: 5, hostname: 'isp-edge1.net', ip: '203.0.114.13', pingTime: 44, ttl: 5},
                {hop: 6, hostname: 'cdn-pop1.net', ip: '203.0.114.14', pingTime: 54, ttl: 6},
                {hop: 7, hostname: 'cdn-edge1.net', ip: '203.0.114.15', pingTime: 64, ttl: 7},
                {hop: 8, hostname: 'destination.com', ip: '198.51.100.60', pingTime: 74, ttl: 8}
            ]
        },
        {
            id: 9,
            name: 'Sample Route 9',
            hops: [
                {hop: 1, hostname: 'router.local', ip: '192.168.8.1', pingTime: 3, ttl: 1},
                {hop: 2, hostname: 'isp-pop1.net', ip: '203.0.114.50', pingTime: 13, ttl: 2},
                {hop: 3, hostname: 'isp-pop2.net', ip: '203.0.114.51', pingTime: 23, ttl: 3},
                {hop: 4, hostname: 'isp-pop3.net', ip: '203.0.114.52', pingTime: 33, ttl: 4},
                {hop: 5, hostname: 'exchange1.net', ip: '203.0.114.53', pingTime: 43, ttl: 5},
                {hop: 6, hostname: 'exchange2.net', ip: '203.0.114.54', pingTime: 53, ttl: 6},
                {hop: 7, hostname: 'destination.com', ip: '198.51.100.70', pingTime: 63, ttl: 7},
                {hop: 8, hostname: 'server.destination.com', ip: '198.51.100.71', pingTime: 73, ttl: 8},
                {hop: 9, hostname: 'final.destination.com', ip: '198.51.100.72', pingTime: 83, ttl: 9},
                {hop: 10, hostname: 'end.destination.com', ip: '198.51.100.73', pingTime: 93, ttl: 10}
            ]
        },
        {
            id: 10,
            name: 'Sample Route 10',
            hops: [
                {hop: 1, hostname: 'gateway.local', ip: '192.168.9.1', pingTime: 2, ttl: 1},
                {hop: 2, hostname: 'isp-edge1.net', ip: '203.0.114.100', pingTime: 12, ttl: 2},
                {hop: 3, hostname: 'isp-core1.net', ip: '203.0.114.101', pingTime: 22, ttl: 3},
                {hop: 4, hostname: 'isp-peer1.net', ip: '203.0.114.102', pingTime: 32, ttl: 4},
                {hop: 5, hostname: 'cdn-edge1.net', ip: '203.0.114.103', pingTime: 42, ttl: 5},
                {hop: 6, hostname: 'cdn-core1.net', ip: '203.0.114.104', pingTime: 52, ttl: 6},
                {hop: 7, hostname: 'destination.com', ip: '198.51.100.80', pingTime: 62, ttl: 7}
            ]
        },
        {
            id: 11,
            name: 'Sample Route 11',
            hops: [
                {hop: 1, hostname: 'home.local', ip: '192.168.10.1', pingTime: 3, ttl: 1},
                {hop: 2, hostname: 'isp-router1.net', ip: '203.0.114.150', pingTime: 13, ttl: 2},
                {hop: 3, hostname: 'isp-core1.net', ip: '203.0.114.151', pingTime: 23, ttl: 3},
                {hop: 4, hostname: 'isp-peer1.net', ip: '203.0.114.152', pingTime: 33, ttl: 4},
                {hop: 5, hostname: 'exchange1.net', ip: '203.0.114.153', pingTime: 43, ttl: 5},
                {hop: 6, hostname: 'destination-edge.com', ip: '198.51.100.90', pingTime: 53, ttl: 6},
                {hop: 7, hostname: 'destination-core.com', ip: '198.51.100.91', pingTime: 63, ttl: 7},
                {hop: 8, hostname: 'destination-final.com', ip: '198.51.100.92', pingTime: 73, ttl: 8}
            ]
        },
        {
            id: 12,
            name: 'Sample Route 12',
            hops: [
                {hop: 1, hostname: 'router.local', ip: '192.168.11.1', pingTime: 4, ttl: 1},
                {hop: 2, hostname: 'isp-access1.net', ip: '203.0.114.200', pingTime: 14, ttl: 2},
                {hop: 3, hostname: 'isp-core1.net', ip: '203.0.114.201', pingTime: 24, ttl: 3},
                {hop: 4, hostname: 'isp-core2.net', ip: '203.0.114.202', pingTime: 34, ttl: 4},
                {hop: 5, hostname: 'isp-edge1.net', ip: '203.0.114.203', pingTime: 44, ttl: 5},
                {hop: 6, hostname: 'destination.com', ip: '198.51.100.100', pingTime: 54, ttl: 6}
            ]
        }
    ]
}

// Perform a traceroute
const performTraceroute = async () => {
    if (!host.value) {
        error.value = 'Please enter a host to trace'
        return
    }

    error.value = null
    isLoading.value = true

    try {
        const response = await axios.post('/api/traceroute', {
            host: host.value,
            name: routeName.value || `Trace to ${host.value}`,
            max_hops: maxHops.value,
            timeout: timeout.value
        })

        if (response.data.success) {
            traceroutes.value.push(response.data.data)
        } else {
            error.value = response.data.message || 'An error occurred during traceroute'
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to perform traceroute'
        console.error('Traceroute error:', err)
    } finally {
        isLoading.value = false
    }
}

// Perform traceroutes in series to popular websites
const performSeriesTraceroutes = async () => {
    const websites = [
        'google.com',
        'yahoo.com',
        'reddit.com',
        'tubitv.com',
        'facebook.com'
    ]

    error.value = null
    isLoading.value = true

    try {
        for (const site of websites) {
            const response = await axios.post('/api/traceroute', {
                host: site,
                name: `Trace to ${site}`,
                max_hops: maxHops.value,
                timeout: timeout.value
            })

            if (response.data.success) {
                traceroutes.value.push(response.data.data)
            } else {
                console.error(`Error tracing ${site}: ${response.data.message || 'An error occurred'}`)
            }
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to perform series traceroutes'
        console.error('Series traceroute error:', err)
    } finally {
        isLoading.value = false
    }
}

// Clear all traceroutes
const clearTraceroutes = () => {
    traceroutes.value = []
}

// Remove a specific traceroute
const removeTraceroute = (id) => {
    traceroutes.value = traceroutes.value.filter(route => route.id !== id)
}

// Load sample data if no traceroutes are provided
onMounted(() => {
    if (traceroutes.value.length === 0) {
        // Uncomment to load sample data by default
        // loadSampleData()
    }
})
</script>

<template>
    <Head :title="title"/>

    <div class="traceroute-container">
        <div class="controls">
            <h1>{{ title }}</h1>
            <p>Traceroute Visualization</p>

            <!-- Traceroute Form -->
            <div class="traceroute-form">
                <h3>Perform Traceroute</h3>
                <div class="form-group">
                    <label for="host">Host:</label>
                    <input
                        type="text"
                        id="host"
                        v-model="host"
                        placeholder="e.g., google.com"
                        :disabled="isLoading"
                    />
                </div>

                <div class="form-group">
                    <label for="routeName">Name (optional):</label>
                    <input
                        type="text"
                        id="routeName"
                        v-model="routeName"
                        placeholder="e.g., Google Trace"
                        :disabled="isLoading"
                    />
                </div>

                <div class="form-row">
                    <div class="form-group half">
                        <label for="maxHops">Max Hops:</label>
                        <input
                            type="number"
                            id="maxHops"
                            v-model="maxHops"
                            min="1"
                            max="64"
                            :disabled="isLoading"
                        />
                    </div>

                    <div class="form-group half">
                        <label for="timeout">Timeout (s):</label>
                        <input
                            type="number"
                            id="timeout"
                            v-model="timeout"
                            min="1"
                            max="30"
                            :disabled="isLoading"
                        />
                    </div>
                </div>

                <div class="error-message" v-if="error">{{ error }}</div>

                <div class="form-actions">
                    <button
                        @click="performTraceroute"
                        class="trace-btn"
                        :disabled="isLoading || !host"
                    >
                        <span v-if="isLoading">Tracing...</span>
                        <span v-else>Trace Route</span>
                    </button>

                    <button
                        @click="loadSampleData"
                        class="sample-btn"
                        :disabled="isLoading"
                    >
                        Load Sample Data
                    </button>

                    <button
                        @click="performSeriesTraceroutes"
                        class="series-btn"
                        :disabled="isLoading"
                    >
                        Trace Popular Sites
                    </button>
                </div>
            </div>

            <div class="legend">
                <h3>Color Legend</h3>
                <div class="legend-item">
                    <div class="color-box green"></div>
                    <span>Low Ping (< 30ms)</span>
                </div>
                <div class="legend-item">
                    <div class="color-box yellow"></div>
                    <span>Medium Ping (30-70ms)</span>
                </div>
                <div class="legend-item">
                    <div class="color-box red"></div>
                    <span>High Ping (> 70ms)</span>
                </div>
                <div class="legend-item">
                    <div class="color-box white"></div>
                    <span>Host (First Hop)</span>
                </div>
                <div class="legend-item">
                    <div class="color-box blue"></div>
                    <span>Target (Last Hop)</span>
                </div>
            </div>

            <div class="opacity-control">
                <h3>Label Opacity</h3>
                <div class="slider-container">
                    <input
                        type="range"
                        min="0"
                        max="1"
                        step="0.1"
                        v-model="labelOpacity"
                        class="opacity-slider"
                    />
                    <span>{{ Math.round(labelOpacity * 100) }}%</span>
                </div>
            </div>

            <div class="force-controls">
                <h3>Force Controls</h3>
                <div class="force-slider-container">
                    <label>Repulsion: {{ repulsionForce }}</label>
                    <div class="slider-container">
                        <input
                            type="range"
                            min="0"
                            max="10"
                            step="0.1"
                            v-model="repulsionForce"
                            class="force-slider"
                        />
                    </div>
                </div>
                <div class="force-slider-container">
                    <label>Attraction: {{ attractionForce }}</label>
                    <div class="slider-container">
                        <input
                            type="range"
                            min="0"
                            max="10"
                            step="0.1"
                            v-model="attractionForce"
                            class="force-slider"
                        />
                    </div>
                </div>
            </div>

            <div class="routes-list">
                <h3>Active Routes</h3>
                <div v-if="traceroutes.length === 0" class="no-routes">
                    No routes to display. Perform a traceroute or load sample data.
                </div>
                <ul v-else>
                    <li v-for="route in traceroutes" :key="route.id" class="route-item">
                        <span class="route-name">{{ route.name }}</span>
                        <span class="route-info">({{ route.hops.length }} hops)</span>
                        <button @click="removeTraceroute(route.id)" class="remove-btn">×</button>
                    </li>
                </ul>
                <button
                    v-if="traceroutes.length > 0"
                    @click="clearTraceroutes"
                    class="clear-btn"
                    :disabled="isLoading"
                >
                    Clear All
                </button>
            </div>
        </div>

        <TracerouteVisualization
            :traceroutes="traceroutes"
            :label-opacity="labelOpacity"
            :repulsion-force="Number(repulsionForce)"
            :attraction-force="Number(attractionForce)"
            class="visualization"
        />
    </div>
</template>

<style scoped>
.traceroute-container {
    display: flex;
    flex-direction: column;
    height: 100vh;
    width: 100vw;
    overflow: hidden;
}

.controls {
    position: absolute;
    top: 20px;
    left: 20px;
    z-index: 10;
    background-color: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 15px;
    border-radius: 5px;
    max-width: 350px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
}

.visualization {
    width: 100%;
    height: 100%;
}

/* Traceroute Form Styles */
.traceroute-form {
    background-color: rgba(255, 255, 255, 0.1);
    padding: 15px;
    border-radius: 4px;
    margin-top: 15px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 12px;
}

.form-row {
    display: flex;
    gap: 10px;
    margin-bottom: 12px;
}

.half {
    flex: 1;
}

label {
    display: block;
    margin-bottom: 5px;
    font-size: 14px;
    color: #ccc;
}

input[type="text"],
input[type="number"] {
    width: 100%;
    padding: 8px;
    border-radius: 4px;
    border: 1px solid #555;
    background-color: #333;
    color: white;
    font-size: 14px;
}

input[type="text"]:focus,
input[type="number"]:focus {
    outline: none;
    border-color: #4CAF50;
}

.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.trace-btn {
    background-color: #4CAF50;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    flex: 2;
    font-weight: bold;
}

.trace-btn:hover:not(:disabled) {
    background-color: #45a049;
}

.sample-btn {
    background-color: #2196F3;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    flex: 1;
}

.sample-btn:hover:not(:disabled) {
    background-color: #0b7dda;
}

.series-btn {
    background-color: #9c27b0;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    flex: 1;
    margin-top: 10px;
    width: 100%;
}

.series-btn:hover:not(:disabled) {
    background-color: #7b1fa2;
}

.clear-btn {
    background-color: #f44336;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    margin-top: 10px;
    width: 100%;
}

.clear-btn:hover:not(:disabled) {
    background-color: #d32f2f;
}

button:disabled {
    background-color: #555;
    cursor: not-allowed;
    opacity: 0.7;
}

.error-message {
    color: #ff6b6b;
    font-size: 14px;
    margin-top: 5px;
    padding: 8px;
    background-color: rgba(255, 0, 0, 0.1);
    border-radius: 4px;
    border-left: 3px solid #ff6b6b;
}

/* Legend Styles */
.legend {
    margin-top: 20px;
    background-color: rgba(255, 255, 255, 0.1);
    padding: 15px;
    border-radius: 4px;
}

.legend h3 {
    margin-top: 0;
    margin-bottom: 10px;
}

.legend-item {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
}

.color-box {
    width: 20px;
    height: 20px;
    margin-right: 10px;
    border-radius: 3px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
}

.green {
    background-color: #00ff00;
}

.yellow {
    background-color: #ffff00;
}

.red {
    background-color: #ff0000;
}

.white {
    background-color: #ffffff;
}

.blue {
    background-color: #0000ff;
}

/* Opacity Control Styles */
.opacity-control {
    margin-top: 20px;
    background-color: rgba(255, 255, 255, 0.1);
    padding: 15px;
    border-radius: 4px;
}

.opacity-control h3 {
    margin-top: 0;
    margin-bottom: 10px;
}

.slider-container {
    display: flex;
    align-items: center;
    gap: 10px;
}

.opacity-slider {
    flex-grow: 1;
    height: 5px;
    -webkit-appearance: none;
    appearance: none;
    background: #555;
    outline: none;
    border-radius: 5px;
}

.opacity-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 15px;
    height: 15px;
    border-radius: 50%;
    background: #4CAF50;
    cursor: pointer;
}

.opacity-slider::-moz-range-thumb {
    width: 15px;
    height: 15px;
    border-radius: 50%;
    background: #4CAF50;
    cursor: pointer;
}

/* Force Controls Styles */
.force-controls {
    margin-top: 20px;
    background-color: rgba(255, 255, 255, 0.1);
    padding: 15px;
    border-radius: 4px;
}

.force-controls h3 {
    margin-top: 0;
    margin-bottom: 10px;
}

.force-slider-container {
    margin-bottom: 10px;
}

.force-slider {
    flex-grow: 1;
    height: 5px;
    -webkit-appearance: none;
    appearance: none;
    background: #555;
    outline: none;
    border-radius: 5px;
    width: 100%;
    margin-top: 5px;
}

.force-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 15px;
    height: 15px;
    border-radius: 50%;
    background: #2196F3;
    cursor: pointer;
}

.force-slider::-moz-range-thumb {
    width: 15px;
    height: 15px;
    border-radius: 50%;
    background: #2196F3;
    cursor: pointer;
}

/* Routes List Styles */
.routes-list {
    margin-top: 20px;
    background-color: rgba(255, 255, 255, 0.1);
    padding: 15px;
    border-radius: 4px;
}

.routes-list h3 {
    margin-top: 0;
    margin-bottom: 10px;
}

.no-routes {
    font-size: 14px;
    color: #aaa;
    font-style: italic;
    margin-bottom: 10px;
}

.routes-list ul {
    list-style-type: none;
    padding-left: 0;
    margin-bottom: 10px;
}

.route-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px;
    margin-bottom: 5px;
    background-color: rgba(255, 255, 255, 0.05);
    border-radius: 4px;
}

.route-name {
    font-weight: bold;
    margin-right: 5px;
}

.route-info {
    color: #aaa;
    font-size: 12px;
}

.remove-btn {
    background-color: transparent;
    color: #ff6b6b;
    border: none;
    font-size: 18px;
    cursor: pointer;
    padding: 0 5px;
}

.remove-btn:hover {
    color: #ff4040;
}
</style>
