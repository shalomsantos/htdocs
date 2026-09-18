document.getElementById('search').addEventListener('input', function () {
    const term = this.value.trim().toLocaleLowerCase('pt-BR');
    let visible = 0;
    document.querySelectorAll('.project').forEach(card => {
        const show = card.dataset.search.toLocaleLowerCase('pt-BR').includes(term);
        card.classList.toggle('d-none', !show);
        if (show) visible++;
    });
    document.getElementById('empty').classList.toggle('d-none', visible !== 0);
});
