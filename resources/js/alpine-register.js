/*
| Registro seguro de componentes Alpine.
|
| O Livewire inicia o Alpine pelo próprio script, que pode rodar antes deste
| módulo (scripts type="module" são adiados). Por isso:
| - se o Alpine já existe, registramos na hora;
| - também registramos no evento alpine:init, para o caso de ele ainda não existir;
| - elementos que já foram inicializados SEM o componente (corrida perdida)
|   são reinicializados. Elementos ainda não inicializados ficam com o Alpine.
*/

const factories = {};

function brokenElements(names) {
    const selector = names.map((n) => `[x-data^="${n}("]`).join(',');

    return [...document.querySelectorAll(selector)].filter((el) => {
        const stack = el._x_dataStack;
        return stack && !(stack[0] && typeof stack[0].init === 'function');
    });
}

function repair(names) {
    for (const el of brokenElements(names)) {
        window.Alpine.destroyTree(el);
        delete el._x_dataStack;
        window.Alpine.initTree(el);
    }
}

export function registerAlpineData(name, factory) {
    factories[name] = factory;

    if (window.Alpine) {
        window.Alpine.data(name, factory);
        queueMicrotask(() => repair([name]));
    }
}

document.addEventListener('alpine:init', () => {
    for (const [name, factory] of Object.entries(factories)) {
        window.Alpine.data(name, factory);
    }
});

document.addEventListener('livewire:navigated', () => {
    if (window.Alpine) repair(Object.keys(factories));
});
