Cypress.Commands.add('getTheDate', () => {
/*    const date = new Date();

    const dateDay = ('0' + date.getDate()).slice(-2);
    const dateMonth = ('0' + (date.getMonth() + 1)).slice(-2);
    const dateYear = date.getFullYear();
    const dateHours = ('0' + date.getHours()).slice(-2);
    const dateMinutes = ('0' + date.getMinutes()).slice(-2);

    let dateTensHours = parseInt(dateHours.slice()[0]);
    let dateUnitsHours = parseInt(dateHours.slice(-1));
    let dateTensMinutes = parseInt(dateMinutes.slice()[0]);
    let dateUnitsMinutes = parseInt(dateMinutes.slice(-1))

    let dateRegex;

    if (dateTensMinutes === 5 && dateUnitsMinutes === 9) {
        if (dateTensHours === 2 && dateUnitsHours === 3) {
            dateRegex = new RegExp(`${dateDay}/${dateMonth}/${dateYear} (23:59|00:00)`);
        } else {
            dateRegex = new RegExp(`${dateDay}/${dateMonth}/${dateYear} (${dateHours}:59|${parseInt(dateHours) + 1}:00)`);
        }
    } else {
        if (dateTensMinutes === 0) {
            dateRegex = new RegExp(`${dateDay}/${dateMonth}/${dateYear} ${dateHours}:(${dateMinutes}|0${parseInt(dateMinutes) + 1})`);
        } else {
            dateRegex = new RegExp(`${dateDay}/${dateMonth}/${dateYear} ${dateHours}:(${dateMinutes}|${parseInt(dateMinutes) + 1})`);
        }
    }

    return cy.wrap(dateRegex);*/
    Cypress.Commands.add('getTheDate', () => {
        const date = new Date();
        const dateDay = ('0' + date.getDate()).slice(-2);
        const dateMonth = ('0' + (date.getMonth() + 1)).slice(-2);
        const dateYear = date.getFullYear();
        const dateHours = ('0' + date.getHours()).slice(-2);
        const dateMinutes = ('0' + date.getMinutes()).slice(-2);

        const tensHours = parseInt(dateHours[0]);
        const unitsHours = parseInt(dateHours.slice(-1));
        const tensMinutes = parseInt(dateMinutes[0]);
        const unitsMinutes = parseInt(dateMinutes.slice(-1));

        let dateRegex;

        if (tensMinutes === 5 && unitsMinutes === 9) {
            if (tensHours === 2 && unitsHours === 3) {
                dateRegex = new RegExp(`${dateDay}/${dateMonth}/${dateYear} (23:59|00:00)`);
            } else {
                dateRegex = new RegExp(`${dateDay}/${dateMonth}/${dateYear} (${dateHours}:59|${tensHours + 1}:00)`);
            }
        } else {
            const nextMinute = unitsMinutes < 9 ? unitsMinutes + 1 : '0' + (tensMinutes + 1) % 6;
            dateRegex = new RegExp(`${dateDay}/${dateMonth}/${dateYear} ${dateHours}:${dateMinutes}|${nextMinute})`);
        }

        return cy.wrap(dateRegex);
    });

});
