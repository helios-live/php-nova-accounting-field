<!-- @format -->

<template>
	<DefaultField
		:field="currentField"
		:errors="errors"
		:show-help-text="showHelpText"
		:full-width-content="fullWidthContent"
	>
		<template #field>
			<div class="flex flex-wrap items-stretch w-full relative">
				<select
					v-if="currentField.currencies"
					class="flex-shrink leading-normal rounded-r-none form-control form-input form-control-bordered w-14 whitespace-no-wrap text-center"
					:id="currentField.uniqueKey"
					:dusk="field.attribute"
					:disabled="currentlyIsReadonly"
					@change="handleChangeInternal"
					v-model="parsed.currency"
				>
					<option value>N/A</option>
					<option
						v-for="currency in currentField.currencies"
						:key="currency"
						:value="currency"
					>
						{{ currency }}
					</option>
				</select>
				<input
					v-else
					disabled
					class="flex-shrink leading-normal rounded-r-none form-control form-input form-control-bordered bg-gray-200 w-14 whitespace-no-wrap text-center"
					:value="currentField.symbol"
				/>

				<input
					class="flex-shrink flex-grow flex-auto leading-normal w-px rounded-l-none form-control form-input form-control-bordered"
					:id="currentField.uniqueKey"
					:dusk="field.attribute"
					v-bind="extraAttributes"
					:disabled="currentlyIsReadonly"
					@input="handleChangeInternal"
					v-model="parsed.value"
				/>
				{{ parsed }} {{ value }}
			</div>
		</template>
	</DefaultField>
</template>

<script>
	import { DependentFormField, HandlesValidationErrors } from "@/mixins";

	export default {
		mixins: [HandlesValidationErrors, DependentFormField],

		props: ["resourceName", "resourceId", "field"],

		data() {
			var th = this;
			return {
				parsed: (function () {
					var val = JSON.parse(th.field.value);
					if (typeof val === "object") {
						return val;
					} else {
						return {
							value: val,
							currency: null,
						};
					}
				})(),
			};
		},
		computed: {
			defaultAttributes() {
				return {
					type: "number",
					min: this.currentField.min,
					max: this.currentField.max,
					step: this.currentField.step,
					pattern: this.currentField.pattern,
					placeholder:
						this.currentField.placeholder || this.field.name,
					class: this.errorClasses,
					currencies: this.currentField.currencies,
				};
			},
			extraAttributes() {
				const attrs = this.currentField.extraAttributes;

				return {
					// Leave the default attributes even though we can now specify
					// whatever attributes we like because the old number field still
					// uses the old field attributes
					...this.defaultAttributes,
					...attrs,
				};
			},
		},
		methods: {
			onSyncedField() {
				var val = JSON.parse(this.syncedField.value);

				this.parsed.value = val.value;
				this.parsed.currency = val.currency;

				this.handleChangeInternal();
			},
			handleChangeInternal(event) {
				this.currentField.value = this.value;
				this.value = JSON.stringify(this.parsed);
				if (this.parsed.currency == "") {
					this.parsed.currency = null;
				}

				// this.handleChange(event);

				this.emitFieldValueChange(
					this.field.attribute,
					this.currentField.value
				);
			},
		},
	};
</script>
