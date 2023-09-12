# Excel Importer

The Excel Importer allows importing structured Excel files into available
content types.

- **Structured**: Each Sheet in the Excel file should have the same name as the
Content Type into which its contents will be imported. Additionally, the columns
names of the sheet should be identical to the machine names of the Content Type
fields.

- **Entity References**: The name/title of Entity Reference fields (mostly
Taxonomy Terms), should be used. This is actually the pain point that this
module solves as compared to
[CSV Importer](https://www.drupal.org/project/csv_importer) module.


## Features
- [x] Import into Content Types along with Taxonomy References
  - [x] Import Page;
    - [x] URL - `/excel_importer`
    - [x] File Upload field
    - [x] Submit
    - [x] Message Section
- [x] Validation
  - [x] File Type
  - [x] File Size
  - [x] **Sheet** name with Content Type Name
  - [x] **Column** name with Content Type Field
  - [x] **Cell** validate required field
  - [x] **Cell** validate data type (numeric)
  - [x] **Valid Taxonomy Term** make sure
- [x] Settings Page:
  - [x] restrict the list of available destination content types
  - [x] URL - `/admin/config/content/excel_importer`
  - [x] List of available destination content types
  - [x] Config for detail description on form page
- [x] Permission
  - [x] Administer settings
  - [x] Use the Excel Import page
- [x] Help page

## Nice to haves
- [ ] Batch Processing with indicator

## Workflow
1. Upload an Excel file with sheets for each content type you want to import at
`/excel_importer`
2. Parse the document sheet by sheet, https://phpspreadsheet.readthedocs.io/en/latest/topics/reading-and-writing-to-file/
3. Determine the Entity to use, from the ones made available in the Setting
page; `/admin/config/content/excel_importer`
4. Establish the relationship between the Content Type and its Entity References
(taxonomies) exist, i.e. the cell should provide the label (title) which is
converted into a `tid`
5. Create the Entity
6. Validate the Entity; if successful move to **(7)**, else show error message
with as much detail as possible and start from **(1)**.
7. Save the Entity.
8. Show success message, indicating number of entries successfully imported.

## Admin Workflow
1. Go to `/admin/config/content/excel_importer`.
2. From the list of Content Types provided in the Settings from, select the
Content Types you would like to allow users to import Excel data into.
3. Save form.

## Dependencies
This module requires the `phpoffice/phpspreadsheet` library. This is handled as
a composer dependency.

## Observations
- there should be no column with the name "type"
- does not allow creation of taxonomy terms on the fly
- does not handle multi-value fields
- Google SpreadSheets and Numbers exported XLSX files have issues with empty
rows
