<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import * as THREE from 'three'
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js'
import { CSS2DRenderer, CSS2DObject } from 'three/examples/jsm/renderers/CSS2DRenderer.js'

// Props definition
const props = defineProps({
    traceroutes: {
        type: Array,
        required: true
    },
    labelOpacity: {
        type: Number,
        default: 0.7
    },
    repulsionForce: {
        type: Number,
        default: 0.5
    },
    attractionForce: {
        type: Number,
        default: 0.1
    }
})

// Emits
const emit = defineEmits(['update:traceroutes'])

// Reactive state
const container = ref(null)

// Three.js variables
let scene, camera, renderer, labelRenderer, controls
let spheres = []
let lines = [] // Array to store route lines
let sphereMap = new Map() // Map to track spheres by hostname and IP
const sphereRadius = 5
const hopDistance = 50

// Keyboard controls
const moveSpeed = 5
const keysPressed = {
  w: false,
  s: false,
  a: false,
  d: false
}

// Force-directed graph parameters
// Using props for repulsion and attraction forces
// const repulsionForce = 0.5  // Repulsion force between nodes
// const attractionForce = 0.1  // Attraction force for connected nodes
const linkDistance = sphereRadius * 6     // Ideal distance between connected nodes (fixed length for spring behavior)
const centeringForce = -0.01 // Force pulling nodes toward the center
const damping = 0.99         // Velocity damping factor
const maxVelocity = 1     // Maximum velocity cap
const minDistance = sphereRadius * 4 // Minimum distance between sphere centers
// Node physics state
const nodeVelocities = new Map() // Map to store node velocities

// Color mapping based on ping time
const getPingColor = (pingTime) => {
    if (pingTime < 30) return 0x00ff00 // Green for low ping
    if (pingTime < 70) return 0xffff00 // Yellow for medium ping
    return 0xff0000 // Red for high ping
}

// Calculate distance from a point to a line segment
const distancePointToLineSegment = (point, lineStart, lineEnd) => {
    // Use full 3D vectors instead of 2D projection
    const p = point.clone()
    const v = lineStart.clone()
    const w = lineEnd.clone()

    const l2 = v.distanceToSquared(w)
    if (l2 === 0) return p.distanceTo(v)

    const t = Math.max(0, Math.min(1, p.clone().sub(v).dot(w.clone().sub(v)) / l2))
    const projection = v.clone().add(w.clone().sub(v).multiplyScalar(t))

    return p.distanceTo(projection)
}


// Initialize Three.js scene
const initScene = () => {
    // Create scene
    scene = new THREE.Scene()
    scene.background = new THREE.Color(0x111111)

    // Create camera
    camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 5000)
    camera.position.z = 200
    camera.position.y = 150
    camera.position.x = -100

    // Create renderer
    renderer = new THREE.WebGLRenderer({ antialias: true })
    renderer.setSize(window.innerWidth, window.innerHeight)
    container.value.appendChild(renderer.domElement)

    // Create label renderer
    labelRenderer = new CSS2DRenderer()
    labelRenderer.setSize(window.innerWidth, window.innerHeight)
    labelRenderer.domElement.style.position = 'absolute'
    labelRenderer.domElement.style.top = '0px'
    container.value.appendChild(labelRenderer.domElement)

    // Add controls
    controls = new OrbitControls(camera, labelRenderer.domElement)
    controls.enableDamping = true

    // Add lights
    const ambientLight = new THREE.AmbientLight(0xffffff, 0.5)
    scene.add(ambientLight)

    const directionalLight = new THREE.DirectionalLight(0xffffff, 1)
    directionalLight.position.set(1, 1, 1)
    scene.add(directionalLight)

    // Add grid helper
    const gridHelper = new THREE.GridHelper(500, 50, 0x444444, 0x222222)
    scene.add(gridHelper)

    // Create spheres for each hop in each traceroute
    createSpheres()

    // Start animation loop
    animate()
}

// Create spheres for traceroute visualization
const createSpheres = () => {
    // Track existing spheres by hostname and IP to reuse them
    const existingSpheres = new Map();

    // Clear existing spheres and lines
    spheres.forEach(sphere => {
        // Remove and dispose of labels (CSS2DObjects) attached to the sphere
        sphere.children.forEach(child => {
            if (child instanceof CSS2DObject) {
                // Remove the label from the sphere
                sphere.remove(child)
                // Remove the DOM element
                if (child.element && child.element.parentNode) {
                    child.element.parentNode.removeChild(child.element)
                }
            }
        })
        scene.remove(sphere)
    })
    lines.forEach(line => scene.remove(line))
    spheres = []
    lines = []
    sphereMap.clear()

    // Create spheres for each traceroute
    props.traceroutes.forEach((route, routeIndex) => {
        const routePoints = [] // Array to store positions for the route line
        const routeSpheres = [] // Array to store spheres for this route

        // Determine if this is the first or last hop in the route
        const firstHopIndex = 0;
        const lastHopIndex = route.hops.length - 1;

        // Track timeout nodes to handle line coloring
        const timeoutNodes = [];

        route.hops.forEach((hop, hopIndex) => {
            // Skip creating spheres for timeout nodes
            if (hop.hostname === 'Timeout' && hop.ip === '*') {
                // Store the timeout node index for line coloring
                timeoutNodes.push(hopIndex);
                return;
            }

            // Create a composite key using hostname and IP
            const sphereKey = `${hop.hostname}|${hop.ip}`;
            let sphere;

            // Check if we already have a sphere with this hostname and IP
            if (existingSpheres.has(sphereKey)) {
                // Reuse existing sphere
                sphere = existingSpheres.get(sphereKey);

                // Remove any existing labels from the sphere
                sphere.children.forEach(child => {
                    if (child instanceof CSS2DObject) {
                        // Remove the label from the sphere
                        sphere.remove(child)
                        // Remove the DOM element
                        if (child.element && child.element.parentNode) {
                            child.element.parentNode.removeChild(child.element)
                        }
                    }
                });

                // Update sphere data for this route
                sphere.userData = {
                    ...hop,
                    routeId: route.id,
                    routeName: route.name,
                    positionLocked: hop.ttl === 1 || sphere.userData.positionLocked // Keep position locked if it was already locked
                };
            } else {
                // Create a new sphere
                const geometry = new THREE.SphereGeometry(sphereRadius, 32, 32);


                // Set color based on position in route: white for host (first hop), blue for target (last hop)
                let sphereColor;
                if (hopIndex === firstHopIndex) {
                    // Host machine (first hop) - white
                    sphereColor = 0xffffff;
                } else if (hopIndex === lastHopIndex) {
                    // Target (last hop) - blue
                    sphereColor = 0x0000ff;
                } else {
                    // All other hops - based on ping time
                    sphereColor = getPingColor(hop.pingTime);
                }

                const material = new THREE.MeshStandardMaterial({
                    color: sphereColor,
                    metalness: 0.3,
                    roughness: 0.4,
                });

                sphere = new THREE.Mesh(geometry, material);

                // Position sphere based on hop number and route
                // First hop (localhost) is fixed at (0,0,0), other hops positioned in a radial layout
                // Calculate initial y-position based on ping time
                const pingFactor = 0.1; // Adjust this to control the height scaling
                const initialHeight = hop.pingTime;

                if (hop.ttl === 1) {
                    // First hop is always at origin horizontally, but may have height based on ping
                    sphere.position.x = 0;
                    sphere.position.y = initialHeight;
                    sphere.position.z = 0;
                } else {
                    // Position in a radial layout for better force-directed graph starting point
                    // Calculate angle based on route index and hop number
                    const angle = (Math.PI * 2 * routeIndex / props.traceroutes.length) +
                                 (hop.ttl * 0.2); // Small angle increment per hop

                    // Calculate radius based on hop number
                    let radius = hop.ttl * linkDistance * 0.8;

                    // Ensure hop2 starts at a minimum distance from hop1 to prevent overlap
                    if (hop.ttl === 2) {
                        // Use a minimum radius for hop2 that's at least 3 times the sphere radius
                        const minHop2Radius = sphereRadius * 3;
                        radius = Math.max(radius, minHop2Radius);
                    }

                    // Set position using polar coordinates with height based on ping time
                    sphere.position.x = Math.cos(angle) * radius;
                    sphere.position.y = initialHeight; // Set height based on ping time
                    sphere.position.z = Math.sin(angle) * radius;
                }

                // Store hop data with the sphere
                sphere.userData = {
                    ...hop,
                    routeId: route.id,
                    routeName: route.name,
                    positionLocked: hop.ttl === 1 // Lock position for localhost (hop 1)
                };

                // Add to scene
                scene.add(sphere);
                spheres.push(sphere);

                // Store in our map of existing spheres for reuse
                existingSpheres.set(sphereKey, sphere);
            }

            // Add to route spheres and points
            routeSpheres.push(sphere);
            routePoints.push(sphere.position.clone());

            // Add to map for physics calculations
            if (!sphereMap.has(sphereKey)) {
                sphereMap.set(sphereKey, []);
            }
            sphereMap.get(sphereKey).push(sphere)

            // Create label
            const labelDiv = document.createElement('div')
            labelDiv.className = 'sphere-label'

            // Format label text based on available data
            let labelText = ''
            if (hop.hostname === 'Unknown' && hop.ip === 'Unknown') {
                labelText = 'Unknown Host'
            } else if (hop.hostname === hop.ip || hop.ip === 'Unknown') {
                // If hostname and IP are the same or IP is unknown, just show hostname
                labelText = hop.hostname
            } else if (hop.hostname === 'Unknown') {
                // If hostname is unknown but we have IP, just show IP
                labelText = hop.ip
            } else {
                // We have both hostname and IP
                labelText = `${hop.hostname} (${hop.ip})`
            }

            labelDiv.textContent = labelText
            labelDiv.style.backgroundColor = `rgba(0, 0, 0, ${props.labelOpacity})`
            labelDiv.style.color = `rgba(255, 255, 255, ${props.labelOpacity === 0 ? 0 : props.labelOpacity + 0.3})`
            labelDiv.style.display = props.labelOpacity === 0 ? 'none' : 'block'
            labelDiv.style.padding = '2px 5px'
            labelDiv.style.borderRadius = '3px'
            labelDiv.style.fontSize = '12px'

            const label = new CSS2DObject(labelDiv)
            label.position.set(0, sphereRadius + 2, 0)
            sphere.add(label)
        })

        // Create lines connecting the spheres in this route
        if (routePoints.length > 0) {
            // If there are timeout nodes, we need to create multiple line segments
            // with different colors for segments that span timeout nodes

            // Create an array to track the actual hop indices in the original data
            // that correspond to each point in routePoints
            const pointToHopIndex = [];
            let pointIndex = 0;

            route.hops.forEach((hop, hopIndex) => {
                if (hop.hostname !== 'Timeout' || hop.ip !== '*') {
                    pointToHopIndex.push(hopIndex);
                    pointIndex++;
                }
            });

            // If we have timeout nodes, create individual line segments
            if (timeoutNodes.length > 0) {
                for (let i = 0; i < routePoints.length - 1; i++) {
                    const startPoint = routePoints[i];
                    const endPoint = routePoints[i + 1];
                    const startHopIndex = pointToHopIndex[i];
                    const endHopIndex = pointToHopIndex[i + 1];

                    // Check if this segment spans a timeout node
                    const spansTimeout = timeoutNodes.some(timeoutIndex =>
                        timeoutIndex > startHopIndex && timeoutIndex < endHopIndex
                    );

                    // Create line geometry for this segment
                    const segmentPoints = [startPoint, endPoint];
                    const segmentGeometry = new THREE.BufferGeometry().setFromPoints(segmentPoints);

                    // Create line material with appropriate color
                    const segmentMaterial = new THREE.LineBasicMaterial({
                        color: spansTimeout ? 0xff0000 : 0x4488ff, // Red if spans timeout, blue otherwise
                        linewidth: 10,
                        opacity: 1,
                        transparent: true
                    });

                    // Create the line segment and add to scene
                    const lineSegment = new THREE.Line(segmentGeometry, segmentMaterial);
                    scene.add(lineSegment);
                    lines.push(lineSegment);

                    // Store reference to the spheres this line segment connects
                    lineSegment.userData = {
                        routeId: route.id,
                        routeName: route.name,
                        spheres: [routeSpheres[i], routeSpheres[i + 1]],
                        spansTimeout: spansTimeout
                    };
                }
            } else {
                // No timeout nodes, create a single line as before
                if (routePoints.length > 1) {
                    // Create line geometry from the points
                    const lineGeometry = new THREE.BufferGeometry().setFromPoints(routePoints);

                    // Create line material
                    const lineMaterial = new THREE.LineBasicMaterial({
                        color: 0x4488ff,
                        linewidth: 2,
                        opacity: 0.7,
                        transparent: true
                    });

                    // Create the line and add to scene
                    const line = new THREE.Line(lineGeometry, lineMaterial);
                    scene.add(line);
                    lines.push(line);

                    // Store reference to the spheres this line connects
                    line.userData = {
                        routeId: route.id,
                        routeName: route.name,
                        spheres: routeSpheres
                    };
                }
            }
        }
    })

    // Initialize velocities for all spheres
    initNodeVelocities()
}

// Initialize or reset node velocities
const initNodeVelocities = () => {
    nodeVelocities.clear()
    spheres.forEach(sphere => {
        nodeVelocities.set(sphere.id, new THREE.Vector3(0, 0, 0))
    })
}

// Apply force-directed graph physics to spheres
const updatePhysics = () => {



    // Initialize velocities if needed
    if (nodeVelocities.size === 0) {
        initNodeVelocities()
    }
    const deltaTime = 1/60 // Assuming 60 FPS, or use actual delta time

    // Process each sphere
    spheres.forEach(sphere => {
        // Skip if this sphere's position is locked
        if (sphere.userData.positionLocked) return

        // Get current velocity or initialize if not exists
        let velocity = nodeVelocities.get(sphere.id)
        if (!velocity) {
            velocity = new THREE.Vector3(0, 0, 0)
            nodeVelocities.set(sphere.id, velocity)
        }

        // Apply forces

        // 1. Repulsion force from all other nodes
        const repulsionVector = new THREE.Vector3(0, 0, 0)

        spheres.forEach(otherSphere => {
            if (sphere === otherSphere) return

            // Calculate distance and direction
            const distance = sphere.position.distanceTo(otherSphere.position)
            if (distance === 0) return // Avoid division by zero

            const direction = new THREE.Vector3()
            direction.subVectors(sphere.position, otherSphere.position)

            // Only use horizontal components (x and z)
            const horizontalDirection = new THREE.Vector3(direction.x, 0, direction.z)
            if (horizontalDirection.length() === 0) return // Avoid normalization of zero vector

            horizontalDirection.normalize()

            // Calculate repulsion force (inverse square law)
            // Stronger when closer, weaker when further away
            let forceMagnitude = props.repulsionForce / Math.max(distance * distance, 0.1)

            // Removed extra repulsion force to allow spheres to move through each other
            // const minDistance = sphereRadius * 2 // Minimum distance between sphere centers
            if (distance < minDistance) {
                // Apply a much stronger repulsion force when spheres are too close
                // The closer they are, the stronger the force
                const overlapFactor = 1 - (distance / minDistance)
                forceMagnitude += props.repulsionForce * 10 * overlapFactor * overlapFactor
            }

            // Add to total repulsion
            repulsionVector.add(horizontalDirection.multiplyScalar(forceMagnitude))
        })

        // 2. Attraction force to connected nodes (previous and next hops in the same route)
        const attractionVector = new THREE.Vector3(0, 0, 0)

        // Find connected nodes through lines
        lines.forEach(line => {
            if (!line.userData.spheres || !line.userData.spheres.includes(sphere)) return

            const connectedSpheres = line.userData.spheres
            const sphereIndex = connectedSpheres.indexOf(sphere)

            // Process connections to previous and next hops
            const neighbors = []
            if (sphereIndex > 0) neighbors.push(connectedSpheres[sphereIndex - 1]) // Previous hop
            if (sphereIndex < connectedSpheres.length - 1) neighbors.push(connectedSpheres[sphereIndex + 1]) // Next hop

            neighbors.forEach(neighbor => {
                // Calculate distance and direction
                const distance = sphere.position.distanceTo(neighbor.position)
                if (distance === 0) return // Avoid division by zero

                const direction = new THREE.Vector3()
                direction.subVectors(neighbor.position, sphere.position)

                // Only use horizontal components (x and z)
                const horizontalDirection = new THREE.Vector3(direction.x, 0, direction.z)
                if (horizontalDirection.length() === 0) return // Avoid normalization of zero vector

                horizontalDirection.normalize()

                // Calculate attraction force with fixed length spring behavior
                // Force is proportional to the difference from the ideal distance
                const displacement = distance - linkDistance
                // Use absolute displacement for fixed length spring behavior
                // This makes the spring force equally strong whether too close or too far
                let forceMagnitude = props.attractionForce * displacement

                // Apply a stronger restoring force when far from ideal distance
                const stiffnessFactor = 1.0 + Math.abs(displacement) / linkDistance
                forceMagnitude *= stiffnessFactor

                // Since we're allowing spheres to move through each other, we don't need to reduce attraction force
                const minDistance = sphereRadius * 2
                if (distance < minDistance) {
                    // Gradually reduce attraction force as nodes get closer
                    const reductionFactor = distance / minDistance
                    forceMagnitude *= reductionFactor
                }

                // Add to total attraction
                attractionVector.add(horizontalDirection.multiplyScalar(forceMagnitude))
            })

            // Special case: if this node has the same hostname and IP as another node,
            // apply additional attraction to maintain proper spacing based on hop count
            // if (sphereIndex > 0) {
            //     const previousHop = connectedSpheres[sphereIndex - 1]
            //
            //     if (sphere.userData.hostname === previousHop.userData.hostname &&
            //         sphere.userData.ip === previousHop.userData.ip) {
            //
            //         // Calculate desired separation based on hop count difference
            //         const hopDiff = Math.abs(sphere.userData.ttl - previousHop.userData.ttl)
            //         const desiredDistance = hopDiff * linkDistance
            //
            //         // Calculate current distance
            //         const currentDistance = sphere.position.distanceTo(previousHop.position)
            //         if (currentDistance === 0) return // Avoid division by zero
            //
            //         // Calculate direction
            //         const direction = new THREE.Vector3()
            //         direction.subVectors(previousHop.position, sphere.position)
            //
            //         // Only use horizontal components
            //         const horizontalDirection = new THREE.Vector3(direction.x, 0, direction.z)
            //         if (horizontalDirection.length() === 0) return // Avoid normalization of zero vector
            //
            //         horizontalDirection.normalize()
            //
            //         // Calculate force to maintain proper spacing with fixed length spring behavior
            //         const displacement = currentDistance - desiredDistance
            //         // Use a stronger base attraction for same host/IP
            //         let forceMagnitude = props.attractionForce * 2 * displacement
            //
            //         // Apply a stronger restoring force when far from ideal distance
            //         const stiffnessFactor = 1.0 + Math.abs(displacement) / desiredDistance
            //         forceMagnitude *= stiffnessFactor
            //
            //         // Since we're allowing spheres to move through each other, we don't need to reduce attraction force
            //         // const minDistance = sphereRadius * 2
            //         // if (currentDistance < minDistance) {
            //         //     // Gradually reduce attraction force as nodes get closer
            //         //     const reductionFactor = currentDistance / minDistance
            //         //     forceMagnitude *= reductionFactor
            //         // }
            //
            //         // Add to total attraction
            //         attractionVector.add(horizontalDirection.multiplyScalar(forceMagnitude))
            //
            //         // Check if we should lock position
            //         const tolerance = 0.05 // 5% tolerance
            //         if (Math.abs(displacement) < desiredDistance * tolerance &&
            //             previousHop.userData.positionLocked) {
            //             sphere.userData.positionLocked = true
            //             return // Skip further processing for this sphere
            //         }
            //     }
            //
            //     // Special case: ensure hop2 doesn't overlap with hop1
            //     if (sphere.userData.ttl === 2 && previousHop.userData.ttl === 1) {
            //         // Calculate current distance
            //         const currentDistance = sphere.position.distanceTo(previousHop.position)
            //         if (currentDistance === 0) return // Avoid division by zero
            //
            //         // Define minimum allowed distance between hop1 and hop2
            //         const minHop2Distance = sphereRadius * 3
            //
            //         // If hop2 is too close to hop1, apply a strong repulsion force
            //         if (currentDistance < minHop2Distance) {
            //             // Calculate direction away from hop1
            //             const direction = new THREE.Vector3()
            //             direction.subVectors(sphere.position, previousHop.position)
            //
            //             // Only use horizontal components
            //             const horizontalDirection = new THREE.Vector3(direction.x, 0, direction.z)
            //             if (horizontalDirection.length() === 0) return // Avoid normalization of zero vector
            //
            //             horizontalDirection.normalize()
            //
            //             // Calculate repulsion force (stronger when closer)
            //             const overlapFactor = 1 - (currentDistance / minHop2Distance)
            //             const forceMagnitude = props.repulsionForce * 15 * overlapFactor * overlapFactor
            //
            //             // Add to total repulsion
            //             repulsionVector.add(horizontalDirection.multiplyScalar(forceMagnitude))
            //         }
            //     }
            // }
        })

        // // 3. Centering force to prevent nodes from drifting too far
        // const centeringVector = new THREE.Vector3()
        // centeringVector.subVectors(new THREE.Vector3(0, 0, 0), sphere.position)
        // centeringVector.y = 0 // Keep horizontal
        // centeringVector.multiplyScalar(centeringForce)


        // 5. Directional bias forces based on hop distance and ping time
        const biasFactor = 0.15// Adjust this value to control the strength of the bias
        //
        // // Rightward bias based on hop distance (ttl)
        // const rightwardBiasVector = new THREE.Vector3()
        // if (sphere.userData.ttl) {
        //     // Apply stronger rightward force for higher hop counts
        //     const hopBias = sphere.userData.ttl * biasFactor
        //     rightwardBiasVector.set(hopBias, 0, 0)
        // }

        // Upward bias based on ping time
        const upwardBiasVector = new THREE.Vector3()
        if (sphere.userData.pingTime) {
            // Apply stronger upward force for higher ping times
            const pingBias = sphere.userData.pingTime * biasFactor * 0.1
            upwardBiasVector.set(0, pingBias, 0)
        }



        // Combine all forces
        const totalForce = new THREE.Vector3()
        totalForce.add(repulsionVector)
        totalForce.add(attractionVector)
        //totalForce.add(centeringVector)
        //totalForce.add(rightwardBiasVector)
        totalForce.add(upwardBiasVector)

        // Update velocity using forces (F = ma, assuming mass = 1)
        velocity.add(totalForce)

        // Apply damping to gradually reduce velocity
        velocity.multiplyScalar(damping)
        totalForce.multiplyScalar(deltaTime)
        velocity.add(totalForce)
        velocity.multiplyScalar(1 - damping)


        // Cap maximum velocity
        if (velocity.length() > maxVelocity) {
            velocity.normalize().multiplyScalar(maxVelocity)
        }

        // Update position based on velocity
        sphere.position.add(velocity)

        // Limit maximum upward movement to ping time
        if (sphere.userData.pingTime && sphere.position.y > sphere.userData.pingTime) {
            sphere.position.y = sphere.userData.pingTime;
        }


    })
}

// Update line positions based on connected spheres
const updateLines = () => {
    lines.forEach(line => {
        if (line.geometry) {
            const oldGeometry = line.geometry
            const points = line.userData.spheres.map(sphere => sphere.position.clone())
            line.geometry = new THREE.BufferGeometry().setFromPoints(points)
            oldGeometry.dispose() // Properly dispose of old geometry
        }
    })
}


// Handle keyboard movement
const handleKeyboardMovement = () => {
    // Get camera direction vectors
    const forward = new THREE.Vector3(0, 0, -1).applyQuaternion(camera.quaternion)
    const right = new THREE.Vector3(1, 0, 0).applyQuaternion(camera.quaternion)

    // Remove vertical component to keep movement in horizontal plane
    forward.y = 0
    right.y = 0

    // Normalize vectors to ensure consistent movement speed
    if (forward.length() > 0) forward.normalize()
    if (right.length() > 0) right.normalize()

    // Calculate movement vectors
    let movement = new THREE.Vector3(0, 0, 0)

    if (keysPressed.w) movement.add(forward.clone().multiplyScalar(moveSpeed))
    if (keysPressed.s) movement.sub(forward.clone().multiplyScalar(moveSpeed))
    if (keysPressed.a) movement.sub(right.clone().multiplyScalar(moveSpeed))
    if (keysPressed.d) movement.add(right.clone().multiplyScalar(moveSpeed))

    // Apply movement to both camera and controls target
    if (movement.length() > 0) {
        camera.position.add(movement)
        controls.target.add(movement)
    }

    // Update controls
    controls.update()
}

// Animation loop
const animate = () => {
    requestAnimationFrame(animate)

    // Update physics
    updatePhysics()

    // Update lines to follow spheres
    updateLines()

    // Handle keyboard movement
    handleKeyboardMovement()

    // Update controls
    controls.update()

    // Render scene
    renderer.render(scene, camera)
    labelRenderer.render(scene, camera)
}

// Handle window resize
const handleResize = () => {
    camera.aspect = window.innerWidth / window.innerHeight
    camera.updateProjectionMatrix()
    renderer.setSize(window.innerWidth, window.innerHeight)
    labelRenderer.setSize(window.innerWidth, window.innerHeight)
}

// Handle key down events
const handleKeyDown = (event) => {
    // Check if the key is one of WSAD (both lowercase and uppercase)
    const key = event.key.toLowerCase()
    if (['w', 's', 'a', 'd'].includes(key)) {
        keysPressed[key] = true
    }
}

// Handle key up events
const handleKeyUp = (event) => {
    // Check if the key is one of WSAD (both lowercase and uppercase)
    const key = event.key.toLowerCase()
    if (['w', 's', 'a', 'd'].includes(key)) {
        keysPressed[key] = false
    }
}

// Lifecycle hooks
onMounted(() => {
    initScene()
    window.addEventListener('resize', handleResize)

    // Add keyboard event listeners
    window.addEventListener('keydown', handleKeyDown)
    window.addEventListener('keyup', handleKeyUp)
})

onBeforeUnmount(() => {
    window.removeEventListener('resize', handleResize)

    // Remove keyboard event listeners
    window.removeEventListener('keydown', handleKeyDown)
    window.removeEventListener('keyup', handleKeyUp)

    // Clean up all spheres and their labels
    if (scene && spheres.length > 0) {
        spheres.forEach(sphere => {
            // Remove and dispose of labels (CSS2DObjects) attached to the sphere
            sphere.children.forEach(child => {
                if (child instanceof CSS2DObject) {
                    // Remove the label from the sphere
                    sphere.remove(child)
                    // Remove the DOM element
                    if (child.element && child.element.parentNode) {
                        child.element.parentNode.removeChild(child.element)
                    }
                }
            })
            scene.remove(sphere)
        })

        // Clear arrays
        spheres = []
        sphereMap.clear()
    }

    // Clean up all lines
    if (scene && lines.length > 0) {
        lines.forEach(line => {
            if (line.geometry) line.geometry.dispose()
            scene.remove(line)
        })
        lines = []
    }

    // Dispose of controls and renderers
    if (controls) controls.dispose()
    if (renderer) renderer.dispose()
    if (labelRenderer) {
        // Remove the labelRenderer's DOM element if it exists
        if (labelRenderer.domElement && labelRenderer.domElement.parentNode) {
            labelRenderer.domElement.parentNode.removeChild(labelRenderer.domElement)
        }
        // Three.js doesn't provide a dispose method for CSS2DRenderer, but we can clean up its DOM element
    }
})

// Watch for changes in traceroutes data
watch(() => props.traceroutes, () => {
    if (scene) createSpheres()
}, { deep: true })

// Watch for changes in label opacity
watch(() => props.labelOpacity, (newOpacity) => {
    // Update all existing labels with new opacity
    if (scene) {
        spheres.forEach(sphere => {
            // Find the label in the sphere's children
            sphere.children.forEach(child => {
                if (child instanceof CSS2DObject) {
                    const labelDiv = child.element;
                    if (labelDiv.className === 'sphere-label') {
                        labelDiv.style.backgroundColor = `rgba(0, 0, 0, ${newOpacity})`;
                        // labelDiv.style.color = `rgba(255, 255, 255, ${newOpacity === 0 ? 0 : newOpacity + 0.3})`;
                        labelDiv.style.color = `rgba(255, 255, 255, ${newOpacity})`;
                        // Update visibility based on opacity
                        labelDiv.style.opacity = newOpacity;
                        // labelDiv.style.display = newOpacity === 0 ? 'none' : 'block';
                        //turn off css2dobject rendering if opacity is 0
                        child.visible = newOpacity > 0;

                    }
                }
            });
        });
    }
})

// Watch for changes in attraction force
watch(() => props.attractionForce, (newAttractionForce) => {
    // No need to update any visual elements directly
    // The force will be applied in the next physics update cycle
    console.log('Attraction force updated:', newAttractionForce);
})

// Watch for changes in repulsion force
watch(() => props.repulsionForce, (newRepulsionForce) => {
    // No need to update any visual elements directly
    // The force will be applied in the next physics update cycle
    console.log('Repulsion force updated:', newRepulsionForce);
})
</script>

<template>
    <div ref="container" class="visualization"></div>
</template>

<style scoped>
.visualization {
    width: 100%;
    height: 100%;
}
</style>
