<template>
  <GuestLayout>
    <div class="w-full px-4 py-10 mx-auto max-w-md">
      <div class="relative flex flex-col min-w-0 break-words w-full mb-6 shadow-lg rounded-lg bg-blueGray-200 border-0">
        <div class="rounded-t mb-0 px-6 py-6">
          <div class="text-center mb-3">
            <h6 class="text-blueGray-500 text-sm font-bold">
              Sign in to your account
            </h6>
          </div>
        </div>
        <div class="flex-auto px-4 lg:px-10 py-10 pt-0">
          <form @submit.prevent="submit">
            <div class="mb-4">
                <label class="block uppercase text-blueGray-600 text-xs font-bold mb-2">
                    Username
                </label>
                <input
                    v-model="form.username"
                    class="border-0 px-3 py-3 placeholder-blueGray-300 text-blueGray-600 bg-white rounded text-sm shadow focus:outline-none w-full ease-linear transition-all duration-150"
                    type="text"
                    required
                    autofocus
                />

            </div>

            <div class="mb-4">
              <label class="block uppercase text-blueGray-600 text-xs font-bold mb-2">
                Password
              </label>
              <input
                v-model="form.password"
                class="border-0 px-3 py-3 placeholder-blueGray-300 text-blueGray-600 bg-white rounded text-sm shadow focus:outline-none w-full ease-linear transition-all duration-150"
                type="password"
                required
                autocomplete="current-password"
              />
            </div>

            <div class="text-center mt-6">
              <button
                class="bg-blueGray-800 text-white active:bg-blueGray-600 text-sm font-bold uppercase px-6 py-3 rounded shadow hover:shadow-lg w-full"
                type="submit"
                :disabled="form.processing"
              >
                Sign In
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </GuestLayout>
</template>

<script setup>
    import GuestLayout from '@/Layouts/GuestLayout.vue'
    import { useForm } from '@inertiajs/vue3'

    const form = useForm({
        username: '',
        password: '',
        remember: false,
    })

    function submit() {
        form.post(route('login'))
    }
</script>
