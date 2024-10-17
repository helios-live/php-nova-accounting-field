<!-- @format -->

<template>
	<PanelItem :index="index" :field="decorated" />
</template>

<script setup>
	import { computed } from "vue";

	const props = defineProps({
		index: {},
		field: { type: Object },
	});

	const decorated = computed(() => {
		let f = props.field;

		if (f.value == null) {
			f.displayedAs = "—";
			return f;
		}
		f.displayedAs =
			'<p class="flex ' +
			f.class +
			" justify-" +
			f.justify +
			'"><span class="mr-1">' +
			f.currency +
			"</span> <span>" +
			f.displayedAs +
			"</span></p>";
		return f;
	});
</script>
