<template>
    <img
        src="/images/logo-jakarta-utara.png"
        alt="Lambang Kota Administrasi Jakarta Utara"
        :class="className || 'w-8 h-8 object-contain'"
        draggable="false"
    />
</template>

<script setup>
defineProps({
    className: {
        type: String,
        default: '',
    },
});
</script>
