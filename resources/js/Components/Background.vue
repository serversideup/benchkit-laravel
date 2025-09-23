<style>
@keyframes flame-dance {
    0%, 100% { transform: scale(1) rotate(0deg); opacity: 0.8; }
    25% { transform: scale(1.1) rotate(1deg); opacity: 0.9; }
    50% { transform: scale(0.95) rotate(-0.5deg); opacity: 0.7; }
    75% { transform: scale(1.05) rotate(0.5deg); opacity: 0.85; }
}

@keyframes ember-float {
    0% { transform: translateY(0px) translateX(0px); opacity: 0; }
    10% { opacity: 1; }
    90% { opacity: 1; }
    100% { transform: translateY(-100vh) translateX(20px); opacity: 0; }
}

@keyframes gradient-shift {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

.flame-orb {
    animation: flame-dance 4s ease-in-out infinite;
}

.flame-orb:nth-child(2) { animation-delay: -1s; }
.flame-orb:nth-child(3) { animation-delay: -2s; }
.flame-orb:nth-child(4) { animation-delay: -3s; }
.flame-orb:nth-child(5) { animation-delay: -0.5s; }

.ember {
    animation: ember-float 8s linear infinite;
}

.ember:nth-child(1) { animation-delay: 0s; left: 10%; }
.ember:nth-child(2) { animation-delay: 2s; left: 20%; }
.ember:nth-child(3) { animation-delay: 4s; left: 80%; }
.ember:nth-child(4) { animation-delay: 6s; left: 70%; }
.ember:nth-child(5) { animation-delay: 1s; left: 50%; }
.ember:nth-child(6) { animation-delay: 3s; left: 30%; }
.ember:nth-child(7) { animation-delay: 5s; left: 90%; }
.ember:nth-child(8) { animation-delay: 7s; left: 60%; }

.animated-gradient {
    background: linear-gradient(-45deg, #1a0f0f, #2d1b15, #3d1a0f, #4a1810, #2d1b15);
    background-size: 400% 400%;
    animation: gradient-shift 12s ease infinite;
}
</style>

<template>
    <div class="fixed inset-0 animated-gradient"></div>

    <!-- Large flame orbs in background -->
    <div class="fixed inset-0 overflow-hidden">
        <!-- Large flame orb - top left -->
        <div class="flame-orb absolute -top-32 -left-32 w-96 h-96 rounded-full bg-gradient-radial from-orange-900/30 via-red-900/20 to-transparent blur-xl"></div>
        
        <!-- Medium flame orb - top right -->
        <div class="flame-orb absolute -top-20 -right-24 w-80 h-80 rounded-full bg-gradient-radial from-yellow-800/25 via-orange-800/15 to-transparent blur-lg"></div>
        
        <!-- Large flame orb - bottom left -->
        <div class="flame-orb absolute -bottom-40 -left-40 w-[500px] h-[500px] rounded-full bg-gradient-radial from-red-800/35 via-orange-900/20 to-transparent blur-2xl"></div>
        
        <!-- Medium flame orb - bottom right -->
        <div class="flame-orb absolute -bottom-24 -right-32 w-72 h-72 rounded-full bg-gradient-radial from-orange-700/30 via-yellow-900/15 to-transparent blur-xl"></div>
        
        <!-- Center flame orb -->
        <div class="flame-orb absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-64 h-64 rounded-full bg-gradient-radial from-red-700/20 via-orange-800/10 to-transparent blur-lg"></div>
    </div>

    <!-- Floating embers -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden">
        <div class="ember absolute w-1 h-1 bg-orange-400 rounded-full opacity-80"></div>
        <div class="ember absolute w-1.5 h-1.5 bg-red-400 rounded-full opacity-60"></div>
        <div class="ember absolute w-0.5 h-0.5 bg-yellow-300 rounded-full opacity-90"></div>
        <div class="ember absolute w-1 h-1 bg-orange-300 rounded-full opacity-70"></div>
        <div class="ember absolute w-1.5 h-1.5 bg-red-300 rounded-full opacity-50"></div>
        <div class="ember absolute w-0.5 h-0.5 bg-yellow-400 rounded-full opacity-80"></div>
        <div class="ember absolute w-1 h-1 bg-orange-500 rounded-full opacity-60"></div>
        <div class="ember absolute w-1.5 h-1.5 bg-red-500 rounded-full opacity-75"></div>
    </div>

    <!-- Subtle overlay for extra depth -->
    <div class="fixed inset-0 bg-gradient-to-br from-black/20 via-transparent to-black/30 pointer-events-none"></div>
</template>