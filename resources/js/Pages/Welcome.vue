<template>
    <div class="min-h-screen bg-base-200 flex items-center justify-center">
        <div class="max-w-4xl mx-auto p-8">
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h1 class="card-title text-4xl mb-4">
                        Welcome to BookStand
                    </h1>
                    <p class="text-base-content/70 mb-6">
                        Inertia.js + Vue 3 + Laravel Boost + DaisyUIが正常に動作しています！
                    </p>

                    <div class="alert alert-info">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <h3 class="font-bold">技術スタック</h3>
                            <div class="text-sm">
                                Laravel {{ laravelVersion }} / Inertia.js / Vue 3 / Laravel Boost / DaisyUI / Tailwind CSS / Vite
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="stats shadow">
                            <div class="stat">
                                <div class="stat-title">Framework</div>
                                <div class="stat-value text-primary">Laravel</div>
                                <div class="stat-desc">Version {{ laravelVersion }}</div>
                            </div>
                        </div>

                        <div class="stats shadow">
                            <div class="stat">
                                <div class="stat-title">Frontend</div>
                                <div class="stat-value text-secondary">Vue 3</div>
                                <div class="stat-desc">with Inertia.js</div>
                            </div>
                        </div>
                    </div>

                    <div class="card-actions justify-end mt-6">
                        <button class="btn btn-primary">Get Started</button>
                        <button class="btn btn-ghost">Learn More</button>
                    </div>
                </div>
            </div>

            <div class="mt-8 text-center">
                <div class="badge badge-lg badge-primary">DaisyUI Components Ready!</div>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    laravelVersion: {
        type: String,
        default: '12.x'
    }
});
</script>
