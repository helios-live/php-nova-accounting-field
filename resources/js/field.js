import IndexField from "./components/IndexField";
import DetailField from "./components/DetailField";
import FormField from "./components/FormField";

Nova.booting((app, store) => {
    console.log("booting");
    app.component("index-php-nova-accounting-field", IndexField);
    app.component("detail-php-nova-accounting-field", DetailField);
    app.component("form-php-nova-accounting-field", FormField);
});
