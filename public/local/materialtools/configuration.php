<?php
// This file is part of Moodle and is distributed under the GNU GPL v3 or later.
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_materialtools_configuration');
$component = 'local_materialtools';
$title = get_string('configurationindex', $component);
$query = trim(optional_param('q', '', PARAM_TEXT));
$view = optional_param('view', 'all', PARAM_ALPHA);
$labels = [];
foreach (['all', 'gui', 'locked', 'external'] as $key) {
    $labels[$key] = get_string('configview' . $key, $component);
}
$view = array_key_exists($view, $labels) ? $view : 'all';
$source = $CFG->root . '/config-dist.php';
$PAGE->set_title($title);
echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo html_writer::tag('p', get_string('configurationindexhelp', $component));
if (!is_readable($source)) {
    echo $OUTPUT->notification(get_string('configexamplemissing', $component));
    echo $OUTPUT->footer();
    return;
}
$names = \local_materialtools\configuration_index::names(file_get_contents($source));
$destinations = \local_materialtools\configuration_index::destinations(admin_get_root(false, true));
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url->out(false), 'class' => 'mb-4']);
echo html_writer::label(get_string('search'), 'config-query');
echo html_writer::empty_tag('input', ['type' => 'search', 'id' => 'config-query', 'name' => 'q',
    'class' => 'form-control mb-2', 'value' => $query]);
echo html_writer::label(get_string('configlocation', $component), 'menuview');
echo html_writer::select($labels, 'view', $view, false, ['class' => 'form-select mb-2']);
echo html_writer::tag('button', get_string('search'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

$table = new html_table();
$table->head = [get_string('configname', $component), get_string('configlocation', $component)];
$table->data = [];
foreach ($names as $name) {
    $entry = $destinations[$name] ?? null;
    $location = $entry ? ($entry['locked'] ? 'locked' : 'gui') : 'external';
    if ($view !== 'all' && $location !== $view) {
        continue;
    }
    if ($query !== '' && \core_text::strpos(\core_text::strtolower($name . ' ' . ($entry['title'] ?? '')),
            \core_text::strtolower($query)) === false) {
        continue;
    }
    $destination = $entry ? html_writer::link($entry['url'], s($entry['title'])) : $labels['external'];
    if ($location === 'locked') {
        $destination .= ' (' . $labels['locked'] . ')';
    }
    $table->data[] = [html_writer::tag('code', s($name)), $destination];
}
echo html_writer::tag('p', get_string('configmatches', $component,
    (object)['shown' => count($table->data), 'total' => count($names)]));
if ($table->data) {
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
