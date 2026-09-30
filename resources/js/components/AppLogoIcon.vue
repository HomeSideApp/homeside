<script setup lang="ts">
defineOptions({
    inheritAttrs: false,
});
</script>

<template>
    <img src="/logo.png" alt="Logo" v-bind="$attrs" />
</template>
