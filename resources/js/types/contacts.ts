export interface ContactValue {
    value: string;
    type: string | null;
    preferred: boolean;
}

export interface ContactDate {
    kind: 'anniversary' | 'custom';
    label: string | null;
    value: string;
    value_type?: string;
}

export interface ContactRelation {
    type:
        | 'parent'
        | 'child'
        | 'sibling'
        | 'spouse'
        | 'friend'
        | 'colleague'
        | 'emergency'
        | 'other';
    related_contact_id: string | null;
    name: string | null;
    external_value?: string | null;
}

export interface ContactFormData {
    household_id: string | null;
    type: 'person' | 'organization';
    display_name: string;
    given_name: string;
    family_name: string;
    additional_name: string;
    nickname: string;
    organization: string;
    job_title: string;
    birthday: string;
    notes: string;
    emails: ContactValue[];
    phones: ContactValue[];
    addresses: ContactValue[];
    urls: ContactValue[];
    dates: ContactDate[];
    relations: ContactRelation[];
    photo: File | null;
    remove_photo: boolean;
}

export interface ContactRecordData {
    id: string;
    source_id: string | null;
    has_photo: boolean;
    photo_checksum: string | null;
    formatted_name: string;
    given_name: string | null;
    family_name: string | null;
    additional_name: string | null;
    nickname: string | null;
    organization: string | null;
    job_title: string | null;
    birthday: string | null;
    notes: string | null;
    emails: ContactValue[];
    phones: ContactValue[];
    addresses: ContactValue[];
    urls: ContactValue[];
    dates: ContactDate[];
    relations: ContactRelation[];
}
