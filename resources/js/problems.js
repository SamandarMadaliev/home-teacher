/**
 * Entry for the Problems section. Each page loads only the code it needs
 * (CodeMirror is heavy), so the solve page and the authoring form are
 * separate lazily-imported modules.
 */
import '../css/problems.css';

if (document.querySelector('[data-problem-solve]')) {
    import('./problems/solve.js');
}
if (document.querySelector('[data-problem-form]')) {
    import('./problems/form.js');
}
