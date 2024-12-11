<!-- @format -->

<template>
	<div v-html="decorated.displayedAs"></div>
</template>

<script setup>
	import { computed } from "vue";

	const props = defineProps({
		index: {},
		field: { type: Object },
	});

	const decorated = computed(() => {
		let f = props.field;

		var val = f.value;

		if (val.value == null) {
			f.displayedAs = "—";
			return f;
		}

		f.displayedAs =
			'<p class="flex ' +
			f.class +
			" justify-" +
			f.justify +
			'"><span class="mr-1">' +
			f.symbol +
			"</span> <span>" +
			f.displayedAs +
			"</span></p>";
		return f;
	});
</script>
