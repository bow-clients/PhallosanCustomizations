
const { Filter } = Shopware;

Filter.register('removeDocTypes', (documentTypes) => {
    return documentTypes.filter(docType =>
        docType.value !== '01916fbc076071f094737f993b44911a' &&
        docType.value !== '0195a407d80c71fa8a16fa50ac6d5364'
    );
});
