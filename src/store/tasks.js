/* eslint-disable no-param-reassign */

import Vue from 'vue';
import TaskPopup from "../components/fragments/TaskPopup";

let handleTask;
let failTask;
let pending = 0;
let ignoreErrors = false;

const pollTask = (store, resolve, reject, delay = 1000, attempt = 1) => {
    setTimeout(() => {
        Vue.http.get('api/task', {
            timeout: 5000 * attempt,
        }).then(
            response => handleTask(response, store, resolve, reject),
            response => failTask(response, store, resolve, reject),
        );
    }, delay);
};

handleTask = (response, store, resolve, reject) => {
    pending = 0;

    if (response.status === 204) {
        resolve();
        return;
    }

    if (!(response.body instanceof Object)) {
        if (!ignoreErrors) {
            store.commit('apiError', response, {root: true});
        }
        reject();
        return;
    }

    const task = response.body;

    store.commit('modals/open', { id: 'current-task', component: TaskPopup, priority: 10 }, { root: true });
    store.commit('setCurrent', task);

    switch (task.status) {
        case 'active':
        case 'aborting':
            pollTask(store, resolve, reject);
            break;

        case 'terminated': // BC
        case 'complete':
            if (task.autoclose && window.localStorage.getItem('contao_manager_autoclose') === '1') {
                store.dispatch('deleteCurrent');
            }
            resolve(task);
            break;

        case 'stopped':
        case 'error':
            reject(task);
            break;

        default:
            reject(task);
            break;
    }
};

failTask = (response, store, resolve, reject) => {
    // Request has timed out
    if (response.status === 0) {
        pending += 1;

        if (pending <= 5) {
            pollTask(store, resolve, reject, 1000, pending + 1);
            return;
        }
    }

    store.commit('setStatus', 'failed');
    reject();
};

export default {
    namespaced: true,

    state: {
        status: null,
        type: null,
        consoleOutput: '',
        current: null,

        deleting: false,
        initialized: false,
    },

    mutations: {
        setStatus(state, status) {
            state.status = status;
        },

        setCurrent(state, task) {
            state.deleting = false;
            state.current = task;
            state.status = task ? task.status : null;
        },

        setDeleting(state, value) {
            state.deleting = !!value;
        },

        setInitialized(state, value) {
            state.initialized = value;
        },
    },

    actions: {
        init(store) {
            const init = () => {
                store.commit('setInitialized', true);
            };
            pollTask(store, init, init);
        },

        execute(store, task) {
            return new Promise((resolve, reject) => {
                if (store.state.status !== null) {
                    reject();
                }

                ignoreErrors = !!task.ignoreErrors;
                delete task.ignoreErrors;

                store.commit('setCurrent', task);
                store.commit('setStatus', 'created');
                store.commit('modals/open', {id: 'current-task', component: TaskPopup, priority: 10 }, { root: true });

                Vue.http.put('api/task', task).then(
                    response => handleTask(response, store, resolve, reject),
                    response => failTask(response, store, resolve, reject),
                );
            });
        },

        abort(store) {
            if (store.state.status === null) {
                return new Promise((resolve, reject) => {
                    reject();
                });
            }

            store.commit('setStatus', 'aborting');

            return Vue.http.patch('api/task', { status: 'aborting' });
        },

        deleteCurrent({ commit, dispatch }, retry = 2) {
            commit('setDeleting', true);
            return Vue.http.delete('api/task').then(
                () => {
                    commit('setCurrent', null);
                    commit('modals/close', 'current-task', { root: true });
                },
                (response) => {
                    // Bad request, there are no tasks
                    if (response.status === 400) {
                        commit('setCurrent', null);
                        commit('modals/close', 'current-task', { root: true });
                        return;
                    }

                    if (response.status === 403 && retry > 0) {
                        return new Promise((resolve) => {
                            setTimeout(() => {
                                resolve(dispatch('deleteCurrent', retry - 1));
                            }, 1000);
                        });
                    }

                    throw response;
                },
            );
        },
    },
};
