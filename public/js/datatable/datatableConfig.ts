export default interface DatatableConfig {
    rowConfig: {
        needsDangerColor: boolean,
        needsRowClickAction: boolean
    },
    domConfig: {
        needsFullDomOverride: boolean,
        needsPartialDomOverride: boolean,
        needsMinimalDomOverride: boolean,
        needsPaginationRemoval: boolean
    },
    drawConfig: {
        needsSearchOverride: boolean,
        needsColumnHide: boolean,
        needsResize: boolean,
        needsEmplacementSearchOverride: boolean,
        callback: () => void,
        filterId: string
    },
    isArticleOrRefSpecifConfig: {
        columns: Array<string>,
        tableFilter: string
    }
}
