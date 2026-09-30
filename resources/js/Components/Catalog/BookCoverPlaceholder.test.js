import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import BookCoverPlaceholder from './BookCoverPlaceholder.vue';

describe('BookCoverPlaceholder', () => {
    it('daje istu pozadinsku boju za isti naslov (deterministički hash)', () => {
        const a = mount(BookCoverPlaceholder, { props: { title: 'Na Drini ćuprija', author: 'Ivo Andrić' } });
        const b = mount(BookCoverPlaceholder, { props: { title: 'Na Drini ćuprija', author: 'Ivo Andrić' } });

        const colorA = a.find('[style]').element.style.backgroundColor;
        const colorB = b.find('[style]').element.style.backgroundColor;

        expect(colorA).not.toBe('');
        expect(colorA).toBe(colorB);
    });

    it('prikazuje <img> kad je prosleđen image URL, bez tipografskog placeholder-a', () => {
        const wrapper = mount(BookCoverPlaceholder, {
            props: { title: 'Prokleta avlija', author: 'Ivo Andrić', image: 'https://example.com/cover.jpg' },
        });

        const img = wrapper.find('img');
        expect(img.exists()).toBe(true);
        expect(img.attributes('src')).toBe('https://example.com/cover.jpg');
        expect(wrapper.text()).not.toContain('Prokleta avlija');
    });

    it('prikazuje tipografski placeholder (naslov + autor) kad nema image URL-a', () => {
        const wrapper = mount(BookCoverPlaceholder, {
            props: { title: 'Seobe', author: 'Miloš Crnjanski' },
        });

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.text()).toContain('Seobe');
        expect(wrapper.text()).toContain('Miloš Crnjanski');
    });

    it('pada nazad na tipografski placeholder kad <img> ne uspe da se učita (slomljen/nedostupan URL)', async () => {
        const wrapper = mount(BookCoverPlaceholder, {
            props: { title: 'Prokleta avlija', author: 'Ivo Andrić', image: 'https://example.com/broken.jpg' },
        });

        expect(wrapper.find('img').exists()).toBe(true);

        await wrapper.find('img').trigger('error');

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.text()).toContain('Prokleta avlija');
        expect(wrapper.text()).toContain('Ivo Andrić');
    });
});
