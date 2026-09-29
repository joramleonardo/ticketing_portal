<template>
  <div>
    <a
      class="text-blueGray-500 block"
      href="#"
      ref="btnDropdownRef"
      @click.prevent="toggleDropdown"
    >
      <div class="items-center flex">
        <span class="w-12 h-12 text-sm text-white bg-blueGray-200 inline-flex items-center justify-center rounded-full"></span>
      </div>
    </a>

    <div
      ref="popoverDropdownRef"
      class="bg-white text-base z-50 float-left py-2 list-none text-left rounded shadow-lg min-w-48"
      :class="{ hidden: !dropdownPopoverShow, block: dropdownPopoverShow }"
    >
      <a class="text-sm py-2 px-4 font-bold block w-full whitespace-nowrap bg-transparent text-black-700">
        {{ $page.props.auth.user.name }}
      </a>

      <div class="h-0 my-2 border border-solid border-blueGray-100" />

      <a
        href="/profile"
        class="text-sm py-2 px-4 font-normal block w-full whitespace-nowrap bg-transparent text-blueGray-700"
      >
        Edit Profile
      </a>

      <a
        href="#"
        @click.prevent="logout"
        class="text-sm py-2 px-4 font-normal block w-full whitespace-nowrap bg-transparent text-blueGray-700"
      >
        Sign Out
      </a>
    </div>
  </div>
</template>

<script>
import { createPopper } from "@popperjs/core"
import { router } from "@inertiajs/vue3"
import image from "@/assets/img/team-1-800x800.jpg"

export default {
  data() {
    return {
      dropdownPopoverShow: false,
      image,
    }
  },
  methods: {
    toggleDropdown(event) {
      event?.preventDefault?.()
      this.dropdownPopoverShow = !this.dropdownPopoverShow
      if (this.dropdownPopoverShow) {
        createPopper(this.$refs.btnDropdownRef, this.$refs.popoverDropdownRef, {
          placement: "bottom-start",
        })
      }
    },
    logout() {
      // optional: close the dropdown immediately
      this.dropdownPopoverShow = false
      // if you have Ziggy, you can use route('logout') instead of '/logout'
      router.post('/logout')
    },
  },
}
</script>
