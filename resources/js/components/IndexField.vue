<template>
  <div v-html="decorated.displayedAs"></div>
</template>

<script setup>

import {computed} from "vue";

const props = defineProps({
    index:{},
    field: {type: Object}
})

const decorated = computed(() => {
    let f = props.field;
    f.displayedAs = '<p class="max-w-xxs flex '+f.class+ ' justify-' + f.justify+'"><span class="mr-1">' + f.currency + "</span> <span>" + f.displayedAs + '</span></p>';
    return f;
});
</script>
