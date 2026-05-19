/** @type {readonly string[]} */
export const ALLOWED_ACCENTS = [
    'blue',
    'red',
    'green',
    'orange',
    'yellow',
    'light_black',
];

export const UserAccentColor = {
    DEFAULT: 'blue',

    /** @param {string | null | undefined} value */
    resolve(value) {
        if (!value) {
            return this.DEFAULT;
        }

        return ALLOWED_ACCENTS.includes(value) ? value : this.DEFAULT;
    },
};
